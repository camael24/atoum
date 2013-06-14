<?php

namespace mageekguy\atoum\tests\units\reports\asynchronous;

use
	mageekguy\atoum,
	mageekguy\atoum\score,
	mageekguy\atoum\mock,
	mageekguy\atoum\reports\asynchronous\coveralls as testedClass
;

require_once __DIR__ . '/../../../runner.php';

class coveralls extends atoum\test
{
	public function beforeTestMethod($method)
	{
		$this->extension('json')->isLoaded();
	}

	public function testClass()
	{
		$this->testedClass->extends('mageekguy\atoum\reports\asynchronous');
	}

	public function testClassConstants()
	{
		$this
			->string(testedClass::defaultServiceName)->isEqualTo('atoum')
			->string(testedClass::defaultEvent)->isEqualTo('manual')
			->string(testedClass::defaultCoverallsUrl)->isEqualTo('https://coveralls.io/api/v1/jobs')
		;
	}

	public function test__construct()
	{
		$this
			->if($report = new testedClass($sourceDir = uniqid(), $token = uniqid()))
			->then
				->array($report->getFields(atoum\runner::runStart))->isEmpty()
				->object($report->getLocale())->isInstanceOf('mageekguy\atoum\locale')
				->object($report->getAdapter())->isInstanceOf('mageekguy\atoum\adapter')
				->array($report->getFields())->isEmpty()
				->castToString($report->getSourceDir())->isEqualTo($sourceDir)
			->if($report = new testedClass($sourceDir, $token, $adapter = new atoum\test\adapter()))
			->then
				->adapter($report->getAdapter())->call('extension_loaded')->withArguments('json')->once()
			->if($adapter->extension_loaded = false)
			->then
				->exception(function() use ($adapter) {
								new testedClass(uniqid(), uniqid(), $adapter);
							}
						)
				->isInstanceOf('mageekguy\atoum\exceptions\runtime')
				->hasMessage('JSON PHP extension is mandatory for coveralls report')
		;
	}

	public function testHandleEvent()
	{
		$this
			->if($adapter = new atoum\test\adapter())
			->if($adapter->extension_loaded = true)
			->and($adapter->exec = function($command) {
				switch($command) {
					case 'git log -1 --pretty=format:\'{"id":"%H","author_name":"%aN","author_email":"%ae","committer_name":"%cN","committer_email":"%ce","message":"%s"}\'':
						return '{"id":"7282ea7620b45fcba0f9d3bfd484ab146aba2bd0","author_name":"mageekguy","author_email":"atoum@atoum.org","comitter_name":"mageekguy","comitter_email":"atoum@atoum.org"}';

					case 'git rev-parse --abbrev-ref HEAD':
						return 'master';

					default:
						return null;
				}
			})
			->and($report = new testedClass($sourceDir = uniqid(), $token = '51bb597d202b4', $adapter))
			->and($score = new \mock\mageekguy\atoum\score())
			->and($coverage = new \mock\mageekguy\atoum\score\coverage())
			->and($writer = new \mock\mageekguy\atoum\writers\file())
			->then
				->when(function() use ($report, $writer) {
						$report->addWriter($writer)->handleEvent(atoum\runner::runStop, new \mageekguy\atoum\runner());
					})
					->mock($writer)->call('writeAsynchronousReport')->withArguments($report)->once()
					->adapter($adapter)->call('file_get_contents')->never()
			->if($adapter->date = '2013-05-13 10:00:00 +0000')
			->and($adapter->file_get_contents = '<?php')
			->and($observable = new \mock\mageekguy\atoum\runner())
			->and($observable->getMockController()->getScore = $score)
			->and($score->getMockController()->getCoverage = $coverage)
			->and($coverage->getMockController()->getClasses = array())
			->and($filepath = join(
				DIRECTORY_SEPARATOR,
				array(
					__DIR__,
					'coveralls',
					'resources',
					'1.json'
				)
			))
			->and($report = new testedClass($sourceDir, $token, $adapter))
			->then
				->object($report->handleEvent(atoum\runner::runStop, $observable))->isIdenticalTo($report)
				->castToString($report)->isEqualToContentsOfFile($filepath)
				->adapter($adapter)->call('file_get_contents')->never()
			->if($coverage->getMockController()->getClasses = array())
			->and($classController = new mock\controller())
			->and($classController->disableMethodChecking())
			->and($classController->__construct = function() {})
			->and($classController->getName = $className = 'bar')
			->and($classController->getFileName = $classFile = 'foo/bar.php')
			->and($classController->getTraits = array())
			->and($classController->getStartLine = 1)
			->and($classController->getEndLine = 12)
			->and($class = new \mock\reflectionClass(uniqid(), $classController))
			->and($methodController = new mock\controller())
			->and($methodController->__construct = function() {})
			->and($methodController->getName = $methodName = 'baz')
			->and($methodController->isAbstract = false)
			->and($methodController->getFileName = $classFile)
			->and($methodController->getDeclaringClass = $class)
			->and($methodController->getStartLine = 4)
			->and($methodController->getEndLine = 8)
			->and($classController->getMethods = array(new \mock\reflectionMethod($className, $methodName, $methodController)))
			->and($coverage->getMockController()->getClasses = array(
				$className => $classFile,
				'foo' => 'bar/foo.php'
			))
			->and($xdebugData = array(
				$classFile =>
				array(
					3 => 1,
					4 => 1,
					5 => 1,
					6 => 0,
					7 => 1,
					8 => 1,
					9 => 1
				)
			))
			->and($filepath = join(
				DIRECTORY_SEPARATOR,
				array(
					__DIR__,
					'coveralls',
					'resources',
					'2.json'
				)
			))
			->and($coverage->setReflectionClassFactory(function() use ($class) { return $class; }))
			->and($coverage->addXdebugDataForTest($this, $xdebugData))
			->then
				->object($report->handleEvent(atoum\runner::runStop, $observable))->isIdenticalTo($report)
				->castToString($report)->isEqualToContentsOfFile($filepath)
				->adapter($adapter)
					->call('file_get_contents')->withArguments(testedClass::defaultCoverallsUrl)->once()
		;
	}
}
