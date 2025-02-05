<?php


use Kirschbaum\PowerJoins\Tests\Models\User;
use Kirschbaum\PowerJoins\Tests\TestCase;

class JoinRelationshipWithCloneTest extends TestCase
{
	/** @test */
	public function test_join_with_clone()
	{
		// If you have a query and clone it, then apply a `joinRelationship()` to *each*
		// of the queries/clones, and only after that executing both queries, then the
		// `JoinsHelper` will think that the join is already applied to both of the
		// queries, whereas they are actually are separate queries. This happening
		// within Filament Tables, when a join is applied to the query and there
		// could be several clones happening based on query scope and counters.
		$query = User::query();
		$queryClone = $query->clone();
		
		$query = $query->joinRelationship('posts');
		$queryClone = $queryClone->joinRelationship('posts');
		
		$this->assertSame(
			$querySql= $query->toSql(),
			$queryCloneSql = $queryClone->toSql()
		);
		
		$this->assertQueryContains('inner join "posts" on "posts"."user_id" = "users"."id"', $querySql);
		
		$this->assertQueryContains('inner join "posts" on "posts"."user_id" = "users"."id"', $queryCloneSql);
	}
}
