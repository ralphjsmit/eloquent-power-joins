<?php

namespace Kirschbaum\PowerJoins;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;

class JoinsHelper
{
	public static array $instances = [];
	
	protected function __construct()
	{
	}
	
	public static function make(): static
	{
		$objects = array_map(fn($object) => spl_object_id($object), func_get_args());
		
		return static::$instances[implode('-', $objects)] ??= new self();
	}
	
	/**
	 * Cache to not join the same relationship twice.
	 */
	private array $joinRelationshipCache = [];
	
	/**
	 * Join method map.
	 */
	public static $joinMethodsMap = [
		'join' => 'powerJoin',
		'leftJoin' => 'leftPowerJoin',
		'rightJoin' => 'rightPowerJoin',
	];
	
	/**
	 * Cache to determine which query model belongs to which query.
	 * This is used to determine if a query is a clone of another
	 * query and therefore if we should refresh the model in it.
	 *
	 * The keys are the spl object IDs of the model, and the
	 * value is the spl object ID of the associated query.
	 */
	public static array $modelQueryDictionary = [];
	
	public static function ensureFreshModel($query): void
	{
		//
	}
	
	public static function shouldRefreshModel($query): bool
	{
		$querySplObjectId = spl_object_id($query);
		$queryModelSplObjectId = spl_object_id($query->getModel());
		
		if ( !isset(static::$modelQueryDictionary[$queryModelSplObjectId]) ) {
			static::$modelQueryDictionary[$queryModelSplObjectId] = $querySplObjectId;
			
			return false;
		}
		
		return tap(static::$modelQueryDictionary[$queryModelSplObjectId] !== $querySplObjectId, function () use ($querySplObjectId, $queryModelSplObjectId) {
			static::$modelQueryDictionary[$queryModelSplObjectId] = $querySplObjectId;
		});
	}
	
	public static function refreshModel($query): void
	{
		$queryModelSplObjectId = spl_object_id($query->getModel());
		
		if ( isset(static::$modelQueryDictionary[$queryModelSplObjectId]) ) {
			static::$modelQueryDictionary[$queryModelSplObjectId] = spl_object_id($query);
		}
	}
	
	
	/**
	 * Format the join callback.
	 */
	public function formatJoinCallback($callback)
	{
		if ( is_string($callback) ) {
			return function ($join) use ($callback) {
				$join->as($callback);
			};
		}
		
		return $callback;
	}
	
	public function generateAliasForRelationship(Relation $relation, string $relationName): array|string
	{
		if ( $relation instanceof BelongsToMany || $relation instanceof HasManyThrough ) {
			return [
				md5($relationName . 'table1' . time()),
				md5($relationName . 'table2' . time()),
			];
		}
		
		return md5($relationName . time());
	}
	
	/**
	 * Get the join alias name from all the different options.
	 */
	public function getAliasName(bool $useAlias, Relation $relation, string $relationName, string $tableName, $callback): string|array|null
	{
		if ( $callback ) {
			if ( is_callable($callback) ) {
				$fakeJoinCallback = new FakeJoinCallback($relation->getBaseQuery(), 'inner', $tableName);
				$callback($fakeJoinCallback);
				
				if ( $fakeJoinCallback->getAlias() ) {
					return $fakeJoinCallback->getAlias();
				}
			}
			
			if ( is_array($callback) && isset($callback[$tableName]) ) {
				$fakeJoinCallback = new FakeJoinCallback($relation->getBaseQuery(), 'inner', $tableName);
				$callback[$tableName]($fakeJoinCallback);
				
				if ( $fakeJoinCallback->getAlias() ) {
					return $fakeJoinCallback->getAlias();
				}
			}
		}
		
		return $useAlias ? $this->generateAliasForRelationship($relation, $relationName) : null;
	}
	
	/**
	 * Checks if the relationship was already joined.
	 */
	public function relationshipAlreadyJoined($model, string $relation): bool
	{
		return isset($this->joinRelationshipCache[spl_object_id($model)][$relation]);
	}
	
	/**
	 * Marks the relationship as already joined.
	 */
	public function markRelationshipAsAlreadyJoined($model, string $relation): void
	{
		$this->joinRelationshipCache[spl_object_id($model)][$relation] = true;
	}
	
	public function clear($model): void
	{
		unset($this->joinRelationshipCache[spl_object_id($model)]);
	}
	
	public function cloneTo($joinsHelper, $oldModel, $newModel): void
	{
		$cacheForModel = $this->joinRelationshipCache[spl_object_id($oldModel)]??[];
		
		$joinsHelper->joinRelationshipCache[spl_object_id($newModel)] = $cacheForModel;
	}
}
