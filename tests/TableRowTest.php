<?php
use Brainvial\TableRow\TableRow;


class TableRowTest extends PHPUnit_Framework_TestCase {


	function testCreateData() {

		$c = new Category();
		$c->title = 'Cat1';
		$c->parent = 0;
		$c->save();
		$c = new Category();
		$c->title = 'Cat2';
		$c->parent = 0;
		$c->save();

		$c = new Category( 1 );


		$this->assertEquals( $c->title, 'Cat1' );

		return $c;
	}

	function testCreateData2() {

		$u = new User();
		$u->email = 'kaka@kaka.com';
		$u->name = 'the user';
		$u->save();

		$a = new Article();
		$a->content = 'article 1';
		$a->timeposted = new \DateTime();
		$a->user = $u;
		$a->save();

		$this->assertEquals( $a->id, 1 );

	}

	function testCreateData3() {
		$a = new Article();
		$a->content = 'article 2 COOL';
		$a->timeposted = new \DateTime();
		$a->user = 9999;
		$a->save();
		$this->assertEquals( $a->id, 2 );
		$a = new Article();
		$a->content = 'article 3';
		$a->timeposted = new \DateTime();
		$a->save();

		$a = new Article();
		$a->content = 'article 4';
		$a->timeposted = new \DateTime();
		$a->save();


		$rel = new RelCategoryArticles();
		$rel->article = 1;
		$rel->category = 1;
		$rel->save();

		$rel = new RelCategoryArticles();
		$rel->article = 2;
		$rel->category = 1;
		$rel->save();

		$rel = new RelCategoryArticles();
		$rel->category = 2;
		$rel->article = 3;
		$rel->save();

		$rel = new RelCategoryArticles();
		$rel->category = 2;
		$rel->article = 2;
		$rel->save();

		$rel = new RelCategoryArticles();
		$rel->category = 2;
		$rel->article = 4;
		$rel->save();


	}

	/**
	 * @depends testCreateData
	 */
	public function testUpdateData( $c ) {
		$c->title = 'Cat1 The Great';
		$c->save();

		$d = new Category( 1 );

		$this->assertEquals( $d->title, 'Cat1 The Great' );
	}


	public function testSelect() {
		$as = Article::select();
		$this->assertEquals( $as->count(), 4 );

		Article::select( "content like ?", [ '%OO%' ] );
		$as = Article::select( "content like ?", [ '%OO%' ], true );
		$this->assertEquals( $as->count(), 1 );

	}

	public function testSelectException() {
		$this->setExpectedException( 'Exception' );
		$as = Article::select( 'afjkhkjfhsdjk' );

	}

	public function testSelectWithoutBind() {
		$as = Article::select( "content like '%COOL%'", true );
		$this->assertEquals( $as->count(), 1 );
		$this->assertEquals( $as[0]->content, 'article 2 COOL' );
	}

	public function testCount() {
		$this->assertEquals( Article::count(), 4 );

		$this->assertEquals( Article::count( "content like ?", [ '%OO%' ] ), 1 );
	}

	public function testSelectOne() {
		$this->assertInstanceOf( 'Article', Article::selectOne( "content like ?", [ '%OO%' ] ) );
		$this->assertNull( Article::selectOne( "content like ?", [ '%NOTCOOL%' ] ) );
	}

	/**
	 * @depends testCreateData
	 */
	public function testIntermediateSelect( Category $c ) {
		$as = $c->getArticles();
		$this->assertEquals( $as->count(), 2 );

		$as = $c->getArticles( "content like ?", [ '%OO%' ] );
		$this->assertEquals( $as->count(), 1 );
	}

	public function testSelectViaRelation() {
		$u = new User( 1 );
		$a = $u->getArticles();
		$this->assertEquals( $a->count(), 1 );

		$a = $u->getArticles( 'content = ?', [ 'asdasd' ] );
		$this->assertEquals( $a->count(), 0 );

		$a = $u->getArticles( "content = 'AACccDD'" );
		$this->assertEquals( $a->count(), 0 );
	}

	public function testEntryExists() {
		$this->assertTrue( Article::entryExists( 1 ) );
		$this->assertFalse( Article::entryExists( 11 ) );
	}

	public function testPreparedQuery() {
		$r = TableRow::preparedQuery("select * from users where ?",[1],true);
		$this->assertEquals( $r->num_rows,1);

		$r = TableRow::preparedQuery("selext * from users where ?",[1],true);
		$this->assertNull( $r);


	}

	public function testLogging() {
		TableRow::$logger = 'sendLog';
		$this->expectOutputString('hello');
		TableRow::toLog('hello');
	}

	function sendlog($s) {
		echo $s;
	}



}
