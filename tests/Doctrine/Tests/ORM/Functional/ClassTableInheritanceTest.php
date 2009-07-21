<?php

namespace Doctrine\Tests\ORM\Functional;

require_once __DIR__ . '/../../TestInit.php';

use Doctrine\Tests\Models\Company\CompanyPerson;
use Doctrine\Tests\Models\Company\CompanyEmployee;
use Doctrine\Tests\Models\Company\CompanyManager;

/**
 * Functional tests for the Class Table Inheritance mapping strategy.
 *
 * @author robo
 */
class ClassTableInheritanceTest extends \Doctrine\Tests\OrmFunctionalTestCase
{
    protected function setUp() {
        $this->useModelSet('company');
        parent::setUp();
    }

    public function testCRUD()
    {        
        $person = new CompanyPerson;
        $person->setName('Roman S. Borschel');
        
        $this->_em->persist($person);

        $employee = new CompanyEmployee;
        $employee->setName('Roman S. Borschel');
        $employee->setSalary(100000);
        $employee->setDepartment('IT');

        $this->_em->persist($employee);

        $employee->setName('Guilherme Blanco');
        $this->_em->flush();

        $this->_em->clear();
        
        $query = $this->_em->createQuery("select p from Doctrine\Tests\Models\Company\CompanyPerson p order by p.id asc");

        $entities = $query->getResultList();

        $this->assertEquals(2, count($entities));
        $this->assertTrue($entities[0] instanceof CompanyPerson);
        $this->assertTrue($entities[1] instanceof CompanyEmployee);
        $this->assertTrue(is_numeric($entities[0]->getId()));
        $this->assertTrue(is_numeric($entities[1]->getId()));
        $this->assertEquals('Roman S. Borschel', $entities[0]->getName());
        $this->assertEquals('Guilherme Blanco', $entities[1]->getName());
        $this->assertEquals(100000, $entities[1]->getSalary());

        $this->_em->clear();
        
        $query = $this->_em->createQuery("select p from Doctrine\Tests\Models\Company\CompanyEmployee p");

        $entities = $query->getResultList();

        $this->assertEquals(1, count($entities));
        $this->assertTrue($entities[0] instanceof CompanyEmployee);
        $this->assertTrue(is_numeric($entities[0]->getId()));
        $this->assertEquals('Guilherme Blanco', $entities[0]->getName());
        $this->assertEquals(100000, $entities[0]->getSalary());

        $this->_em->clear();
        
        //TODO: Test bulk UPDATE
        $query = $this->_em->createQuery("update Doctrine\Tests\Models\Company\CompanyEmployee p set p.name = ?1, p.department = ?2 where p.name='Guilherme Blanco' and p.salary = ?3");
        $query->setParameter(1, 'NewName');
        $query->setParameter(2, 'NewDepartment');
        $query->setParameter(3, 100000);
        $query->getSql();
        $numUpdated = $query->execute();
        $this->assertEquals(1, $numUpdated);
        
        $query = $this->_em->createQuery("delete from Doctrine\Tests\Models\Company\CompanyPerson p");
        $numDeleted = $query->execute();
        $this->assertEquals(2, $numDeleted);
    }
    
    public function testMultiLevelUpdateAndFind() {
    	$manager = new CompanyManager;
        $manager->setName('Roman S. Borschel');
        $manager->setSalary(100000);
        $manager->setDepartment('IT');
        $manager->setTitle('CTO');
        $this->_em->persist($manager);
        $this->_em->flush();
        
        $manager->setName('Roman B.');
        $manager->setSalary(119000);
        $manager->setTitle('CEO');
        $this->_em->persist($manager);
        $this->_em->flush();
        
        $this->_em->clear();
        
        $manager = $this->_em->find('Doctrine\Tests\Models\Company\CompanyManager', $manager->getId());
        
        $this->assertEquals('Roman B.', $manager->getName());
        $this->assertEquals(119000, $manager->getSalary());
        $this->assertEquals('CEO', $manager->getTitle());
        $this->assertTrue(is_numeric($manager->getId()));
    }
    
    public function testSelfReferencingOneToOne() {
    	$manager = new CompanyManager;
        $manager->setName('John Smith');
        $manager->setSalary(100000);
        $manager->setDepartment('IT');
        $manager->setTitle('CTO');
        
        $wife = new CompanyPerson;
        $wife->setName('Mary Smith');
        $wife->setSpouse($manager);
        
        $this->assertSame($manager, $wife->getSpouse());
        $this->assertSame($wife, $manager->getSpouse());
        
        $this->_em->persist($manager);
        $this->_em->persist($wife);
        
        $this->_em->flush();
        
        //var_dump($this->_em->getConnection()->fetchAll('select * from company_persons'));
        //var_dump($this->_em->getConnection()->fetchAll('select * from company_employees'));
        //var_dump($this->_em->getConnection()->fetchAll('select * from company_managers'));
        
        $this->_em->clear();
        
        $query = $this->_em->createQuery('select p, s from Doctrine\Tests\Models\Company\CompanyPerson p join p.spouse s where p.name=\'Mary Smith\'');
        
        $result = $query->getResultList();
        $this->assertEquals(1, count($result));
        $this->assertTrue($result[0] instanceof CompanyPerson);
        $this->assertEquals('Mary Smith', $result[0]->getName());
        $this->assertTrue($result[0]->getSpouse() instanceof CompanyEmployee);
        $this->assertEquals('John Smith', $result[0]->getSpouse()->getName());
        $this->assertSame($result[0], $result[0]->getSpouse()->getSpouse());
    }
    
    public function testSelfReferencingManyToMany()
    {
        $person1 = new CompanyPerson;
        $person1->setName('Roman');
        
        $person2 = new CompanyPerson;
        $person2->setName('Jonathan');
        
        $person1->addFriend($person2);
        
        $this->assertEquals(1, count($person1->getFriends()));
        $this->assertEquals(1, count($person2->getFriends()));
        
        
        $this->_em->persist($person1);
        $this->_em->persist($person2);
        
        $this->_em->flush();
        
        $this->_em->clear();
        
        $query = $this->_em->createQuery('select p, f from Doctrine\Tests\Models\Company\CompanyPerson p join p.friends f where p.name=?1');
        $query->setParameter(1, 'Roman');
        
        $result = $query->getResultList();
        $this->assertEquals(1, count($result));
        $this->assertEquals(1, count($result[0]->getFriends()));
        $this->assertEquals('Roman', $result[0]->getName());
        
        $friends = $result[0]->getFriends();
        $this->assertEquals('Jonathan', $friends[0]->getName());
    }
}