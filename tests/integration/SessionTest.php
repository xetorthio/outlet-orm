<?php

class Integration_SessionTest extends OutletTestCase {
	/**
	 *
	 * @var OutletSession
	 */
	protected $session;

	public function testCanGetOutletPropertiesMapper() {
		$mapper = $this->session->getMapperFor('Project');
		$this->assertThat($mapper, $this->isInstanceOf('OutletPropertiesMapper'));
	}

	public function testCanGetOutletGettersAndSettersMapper() {
		$mapper = $this->session->getMapperFor('Bug');
		$this->assertThat($mapper, $this->isInstanceOf('OutletGettersAndSettersMapper'));
	}

	public function testCacheMappers() {
		$mapper = $this->session->getMapperFor('Bug');
		$this->assertSame($mapper, $this->session->getMapperFor('Bug'));

		$mapper = $this->session->getMapperFor('Project');
		$this->assertSame($mapper, $this->session->getMapperFor('Project'));
	}

	public function testLoadingFromIdentityMap() {
		$this->connection->execute('INSERT INTO bugs (id, name) VALUES (1, "test")');
		$bug = $this->session->load('Bug', 1);
		
		$this->assertSame($bug, $this->session->load('Bug', 1));
	}

	public function testClear() {
		$this->connection->execute('INSERT INTO bugs (id, name) VALUES (1, "test")');
		$bug = $this->session->load('Bug', 1);

		$this->session->clear();

		$this->assertNotSame($bug, $this->session->load('Bug', 1));
	}

	public function testAttachingAndLoading() {
		$entity = new Bug_OutletProxy('name', 1);

		$this->assertTrue($this->session->attach($entity)->isAttached($entity));
		
		$this->assertSame($entity, $this->session->load('Bug', 1));
	}

	public function testCRUD() {		
		$bug = new Bug('name', 1);		
		$bug = $this->session->save($bug)->clear()->load('Bug', 1);		
		$this->assertNotNull($bug);		
		$bug->setName('new name');
		$bug = $this->session->save($bug)->clear()->load('Bug', 1);
		$this->assertEquals('new name', $bug->getName());

		$this->assertNull($this->session->delete($bug)->clear()->load('Bug', 1));
	}

	public function testAttachingAndUpdatingObject() {
		$this->connection->execute('INSERT INTO bugs (id, name) VALUES (1, "test")');
		$bug = new Bug_OutletProxy('test', 1);
		$this->session->attach($bug);
		$bug->setName('new value');
		$bug = $this->session->save($bug)->clear()->load('Bug', 1);

		$this->assertEquals('new value', $bug->getName());
	}

	public function testEntityIsAttachedToSessionAfterInserting() {
		$bug = new Bug('name', 1);

		$this->assertFalse($this->session->isAttached($bug));

		$this->session->save($bug);

		// HACK:
		$this->assertNotNull($this->session->getIdentityMap()->get('Bug', 1));
	}

// TODO: move tests to unit of work
//	public function testGettingModifiedValues() {
//		$this->connection->execute('INSERT INTO bugs (id, name) VALUES (1, "test")');
//		$bug = $this->session->load('Bug', 1);
//
//		$bug->setName('new value');
//
//		$this->assertEquals(array('Name' => 'new value'), $this->session->getModifiedValues($bug));
//	}
//
//	public function testCheckingIsDirty() {
//		$this->connection->execute('INSERT INTO bugs (id, name) VALUES (1, "test")');
//		$bug = $this->session->load('Bug', 1);
//
//		$this->assertFalse($this->session->isDirty($bug));
//
//		$bug->setName('new value');
//
//		$this->assertTrue($this->session->isDirty($bug));
//	}

	public function testAutoUpdate() {
		$this->connection->execute('INSERT INTO bugs (id, name) VALUES (1, "test")');

		$bug = $this->session->load('Bug', 1);
		$bug->setName('new name');
		$this->session->flush();
		$this->session->clear();

		$this->assertEquals('new name', $this->session->load('Bug', 1)->getName());
	}
	
	public function testSaveSubclass() {
		$bug = new FunctionalBug();
		$bug->setID(1);
		$bug->setName("Error while saving subclass :P");
		$bug->setSteps("Executed unit tests... this doesn't make any sense! lol");		
		$this->session->save($bug);
		$this->session->flush();
		$this->session->clear();
		$newbug = $this->session->load("FunctionalBug", 1);
		$this->assertNotNull($newbug);
	} 
	
	public function testLoadSubclassFromSuperclass() {
		$bug = new FunctionalBug();
		$bug->setID(1);
		$bug->setName("Error while saving subclass :P");
		$bug->setSteps("Executed unit tests... this doesn't make any sense! lol");		
		$this->session->save($bug);
		$this->session->flush();
		$this->session->clear();
		$newbug = $this->session->load("Bug", 1);
		$this->assertNotNull($newbug);
		$this->assertThat($newbug, $this->isInstanceOf("FunctionalBug"));
	}
	
	public function testQueryManySubclasses() {
		$b = new Bug();
		$b->setID(1);
		$b->setName("bug");
		
		$fb = new FunctionalBug();
		$fb->setID(2);
		$fb->setName("functional bug");
		$fb->setSteps("some steps");
		
		$tb = new TechnicalBug();
		$tb->setID(3);
		$tb->setName("technical bug");
		$tb->setErrorCode(124);
		
		$this->session->save($b);
		$this->session->save($fb);
		$this->session->save($tb);
		$this->session->flush();		
		$this->session->clear();
		
		$bugs = $this->session->from("Bug")->find();
		
		$this->assertThat($bugs[0], $this->isInstanceOf("Bug_OutletProxy"));
		$this->assertThat($bugs[1], $this->isInstanceOf("FunctionalBug_OutletProxy"));
		$this->assertThat($bugs[2], $this->isInstanceOf("TechnicalBug_OutletProxy"));
	}
	
	public function setUp() {
		$classes = array(
			'Project' => array(
				'table' => 'projects',
				'props' => array(
					'id' => array('id', 'int', array('pk' => true)),
					'name' => array('name', 'varchar')
				)
			),
			'Bug' => array(
				'table' => 'bugs',
				'props' => array(
					'ID' => array('id', 'int', array('pk' => true)),
					'Name' => array('name', 'varchar')
				),
				'discriminator' => array('type', 'varchar'),
				'discriminator-value' => 'unknown',
				'subclasses' => array(
					'TechnicalBug' => array(
						'discriminator-value' => 'technical',
						'props' => array(
							'errorcode' => array('errorcode', 'int')
						),
						'useGettersAndSetters' => true
					),
					'FunctionalBug' => array(
						'discriminator-value' => 'functional',
						'props' => array(
							'steps' => array('steps', 'varchar')
						),
						'useGettersAndSetters' => true
					)
				),
				'useGettersAndSetters' => true
			)
		);
		$this->session = $this->openSession($classes, true);
		$this->connection = $this->session->getConnection();
		$this->connection->execute('CREATE TABLE bugs (id NUMERIC, name TEXT, type TEXT, errorcode NUMERIC, steps TEXT)');
	}
}