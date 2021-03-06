<?php

namespace Snowcap\CoreBundle\Tests\Listener;

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Filesystem\Filesystem;

use Snowcap\CoreBundle\Listener\FileSubscriber;
use Snowcap\CoreBundle\File\CondemnedFile;
use Snowcap\CoreBundle\Tests\Listener\Fixtures\Entity\User;
use Snowcap\CoreBundle\Tests\Listener\Fixtures\Entity\Novel;

class FileSubscriberTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var FileSubscriber
     */
    private $subscriber;

    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var array
     */
    private $classes = array(
        'Snowcap\CoreBundle\Tests\Listener\Fixtures\Entity\User',
        'Snowcap\CoreBundle\Tests\Listener\Fixtures\Entity\Novel',
    );

    /**
     * @return \Doctrine\ORM\EntityManager
     */
    private function buildEntityManager()
    {
        AnnotationRegistry::registerFile(__DIR__ . '/../../Doctrine/Mapping/File.php');

        $config = Setup::createAnnotationMetadataConfiguration(
            array(__DIR__ . '/Fixtures'),
            false,
            \sys_get_temp_dir(),
            null,
            false
        );
        $config->setAutoGenerateProxyClasses(true);

        $params = array(
            'driver' => 'pdo_sqlite',
            'memory' => true,
        );

        return EntityManager::create($params, $config);
    }

    private function createSchema()
    {
        $em = $this->em;
        $schema = array_map(function ($class) use ($em) {
            return $em->getClassMetadata($class);
        }, $this->classes);

        $schemaTool = new SchemaTool($em);
        $schemaTool->dropSchema(array());
        $schemaTool->createSchema($schema);
    }

    protected function setUp()
    {
        $this->em = $this->buildEntityManager();
        $this->createSchema();
        $this->rootDir = sys_get_temp_dir() . '/' . uniqid();

        $this->subscriber = new FileSubscriber($this->rootDir);

        foreach($this->classes as $class) {
            $metaDataEventArgs = new LoadClassMetadataEventArgs($this->em->getClassMetadata($class), $this->em);
            $this->subscriber->loadClassMetadata($metaDataEventArgs);
        }

        parent::setUp();
    }

    /*
    public function testPreFlushInsert()
    {
        $user = $this->buildUserToInsert();
        $eventArgs = new PreFlushEventArgs($this->em);
        $this->subscriber->preFlush($eventArgs);

        $changeset = $this->em->getUnitOfWork()->getEntityChangeSet($user);
        $this->assertArrayHasKey('cv', $changeset);
        $this->assertNotNull($user->getCv());
    }
    */

    /*
    public function testPreFlushInsertForMappedSuperClass()
    {
        $novel = $this->buildNovelToInsert();
        $eventArgs = new PreFlushEventArgs($this->em);
        $this->subscriber->preFlush($eventArgs);

        $changeset = $this->em->getUnitOfWork()->getEntityChangeSet($novel);
        $this->assertArrayHasKey('attachment', $changeset);
        $this->assertNotNull($novel->getAttachment());
    }
    */

    public function testPreFlushUpdate()
    {
        $user = $this->buildUserToUpdate();
        $eventArgs = new PreFlushEventArgs($this->em);
        $this->subscriber->preFlush($eventArgs);

        $changeset = $this->em->getUnitOfWork()->getEntityChangeSet($user);
        $this->assertArrayHasKey('cv', $changeset);
        $this->assertNotNull($user->getCv());
    }

    public function testPostPersist()
    {
        $user = $this->buildUserToInsert();
        $cvPath = 'uploads/cvs/' . uniqid() . '.txt';
        $user->setCv($cvPath);

        $eventArgs = new LifecycleEventArgs($user, $this->em);
        $this->subscriber->postPersist($eventArgs);

        $this->assertNull($user->getCvFile());
        $this->assertFileExists($this->rootDir .'/' . $cvPath);
    }

    public function testPostPersistForMappedSuperClass()
    {
        $novel = $this->buildNovelToInsert();
        $attachmentPath = 'uploads/attachments/' . uniqid() . '.txt';
        $novel->setAttachment($attachmentPath);

        $eventArgs = new LifecycleEventArgs($novel, $this->em);
        $this->subscriber->postPersist($eventArgs);

        $this->assertNull($novel->getAttachmentFile());
        $this->assertFileExists($this->rootDir .'/' . $attachmentPath);
    }

    public function testPostUpdate()
    {
        $user = $this->buildUserToUpdate();
        $cvPath = 'uploads/cvs/' . uniqid() . '.txt';
        $user->setCv($cvPath);

        $eventArgs = new LifecycleEventArgs($user, $this->em);
        $this->subscriber->postUpdate($eventArgs);

        $this->assertNull($user->getCvFile());
        $this->assertFileExists($this->rootDir .'/' . $cvPath);
    }

    public function testPostUpdateWithPrevousFile()
    {
        $user = $this->buildUserToUpdate();
        $oldCvPath = 'uploads/cvs/' . uniqid() . '.txt';
        $newCvPath = 'uploads/cvs/' . uniqid() . '.txt';
        $this->copyFile(__DIR__ . '/Fixtures/files/test_file.txt', '/' . $oldCvPath);
        $user->setCv($newCvPath);

        $this->em->getUnitOfWork()->propertyChanged($user, 'cv', $oldCvPath, $newCvPath);

        $this->assertFileExists($this->rootDir .'/' . $oldCvPath);

        $eventArgs = new LifecycleEventArgs($user, $this->em);
        $this->subscriber->postUpdate($eventArgs);

        $this->assertNull($user->getCvFile());
        $this->assertFileExists($this->rootDir .'/' . $newCvPath);
        $this->assertFileNotExists($this->rootDir .'/' . $oldCvPath);
    }

    public function testPostUpdateWithCondemnedFile()
    {
        $user = $this->buildUserToUpdate();
        $cvPath = 'uploads/cvs/' . uniqid() . '.txt';
        $this->copyFile(__DIR__ . '/Fixtures/files/test_file.txt', '/' . $cvPath);
        $user->setCv($cvPath);

        $this->assertFileExists($this->rootDir .'/' . $cvPath);
        $user->setCvFile(new CondemnedFile());

        $preFlushEventArgs = new PreFlushEventArgs($this->em);
        $eventArgs = new LifecycleEventArgs($user, $this->em);
        $this->subscriber->preFlush($preFlushEventArgs);
        $this->subscriber->postUpdate($eventArgs);

        $this->assertNull($user->getCvFile());
        $this->assertFileNotExists($this->rootDir .'/' . $cvPath);
    }

    public function testPostRemove()
    {
        $user = $this->buildUserToDelete();
        $cvPath = 'uploads/cvs/' . uniqid() . '.txt';
        $this->copyFile(__DIR__ . '/Fixtures/files/test_file.txt', '/' . $cvPath);
        $user->setCv($cvPath);

        $this->assertFileExists($this->rootDir .'/' . $cvPath);

        $eventArgs = new LifecycleEventArgs($user, $this->em);

        $this->subscriber->preRemove($eventArgs);
        $this->subscriber->postRemove($eventArgs);

        $this->assertFileNotExists($this->rootDir .'/' . $cvPath);
    }

    /**
     * @param string $from
     * @param string $to
     * @return string
     */
    private function copyFile($from, $to)
    {
        $fs = new Filesystem();
        $targetPath = $this->rootDir . $to;
        $fs->copy($from, $targetPath);

        return $targetPath;
    }

    /**
     * @return Fixtures\Entity\User
     */
    private function buildUserToInsert()
    {
        $user = new User();
        $user->setUserName('johndoe');
        $user->setCvFile(new File($this->copyFile(__DIR__ . '/Fixtures/files/test_file.txt', '/test_file.txt')));

        $this->em->getUnitOfWork()->scheduleForInsert($user);

        return $user;
    }

    /**
     * @return Fixtures\Entity\Novel
     */
    private function buildNovelToInsert()
    {
        $novel = new Novel();
        $novel->setTitle('Dancing with the frogs');
        $novel->setSubtitle('An epic tale of man-frog love');
        $novel->setAttachmentFile(new File($this->copyFile(__DIR__ . '/Fixtures/files/test_file.txt', '/test_file.txt')));

        $this->em->getUnitOfWork()->scheduleForInsert($novel);

        return $novel;
    }

    /**
     * @return Fixtures\Entity\User
     */
    private function buildUserToUpdate()
    {
        $userName = 'johndoe';
        $cvFile = new File($this->copyFile(__DIR__ . '/Fixtures/files/test_file.txt', '/test_file.txt'));

        $user = new User();
        $user->setUserName($userName);
        $user->setCvFile($cvFile);

        $this->em->getUnitOfWork()->registerManaged($user, array(1), array(
            'userName' => $userName,
            'cvFile' => $cvFile
        ));
        $this->em->getUnitOfWork()->scheduleForUpdate($user);

        return $user;
    }

    /**
     * @return Fixtures\Entity\User
     */
    private function buildUserToDelete()
    {
        $userName = 'johndoe';
        $cvFile = new File($this->copyFile(__DIR__ . '/Fixtures/files/test_file.txt', '/test_file.txt'));

        $user = new User();
        $user->setUserName($userName);
        $user->setCvFile($cvFile);

        $this->em->getUnitOfWork()->registerManaged($user, array(1), array(
            'userName' => $userName,
            'cvFile' => $cvFile
        ));
        $this->em->getUnitOfWork()->scheduleForDelete($user);

        return $user;
    }
}
