<?php
use Mockery as m;

class EloquentRepositoryTest extends PHPUnit_Framework_TestCase
{
	public function tearDown()
	{
		m::close();
	}

	public function testInitialize()
	{
		$m = $this->makeMockModel();
		$v = $this->makeMockValidator();
		$r = $this->makeRepo($m, $v);

		$this->assertInstanceOf('c\EloquentRepository', $r);
		$this->assertSame($m, $r->getModel());
	}

	public function testGetAll()
	{
		list($model, $validator, $repo) = $this->make();
		$query = $this->makeMockQuery();
		$model->shouldReceive('newQuery')->andReturn($query);
		$query->shouldReceive('get')->andReturn('foo');

		$this->assertEquals('foo', $repo->getAll());
	}

	public function testGetAllPaginated()
	{
		list($model, $validator, $repo) = $this->make();
		$query = $this->makeMockQuery();
		$model->shouldReceive('newQuery')->andReturn($query);
		$query->shouldReceive('paginate')->with(20)->andReturn('foo');

		$this->assertEquals('foo', $repo->paginate(20)->getAll());
	}

	public function testFetchManyPrepare()
	{
		list($model, $validator, $repo) = $this->make('RepoWithPrepares');
		$query = $this->makeMockQuery();
		$model->shouldReceive('newQuery')->andReturn($query);
		$query->shouldReceive('prepareQuery')->once();
		$query->shouldReceive('get')->andReturn('foo');

		$this->assertEquals('foo', $repo->getAll());
	}

	public function testFetchManyPaginatedPrepare()
	{
		list($model, $validator, $repo) = $this->make('RepoWithPrepares');
		$query = $this->makeMockQuery();
		$result = m::mock();
		$model->shouldReceive('newQuery')->andReturn($query);
		$query->shouldReceive('prepareQuery')->once();
		$query->shouldReceive('paginate')->andReturn($result);
		$result->shouldReceive('preparePaginator')->once();

		$this->assertSame($result, $repo->paginate(20)->getAll());
	}

	public function testGetByKey()
	{
		list($model, $validator, $repo) = $this->make();
		$query = $this->makeMockQuery();
		$model->shouldReceive('newQuery')->andReturn($query);
		$model->shouldReceive('getQualifiedKeyName');
		$query->shouldReceive('where->first')->andReturn('foo');

		$this->assertEquals('foo', $repo->getByKey(1));
	}

	public function testFetchSinglePrepare()
	{
		list($model, $validator, $repo) = $this->make('RepoWithPrepares');
		$query = $this->makeMockQuery();
		$result = m::mock();
		$model->shouldReceive('getQualifiedKeyName')->once()->andReturn('foo');
		$model->shouldReceive('newQuery->where')->andReturn($query);
		$query->shouldReceive('prepareQuery')->once();
		$query->shouldReceive('first')->once()->andReturn($result);
		$result->shouldReceive('prepareModel')->once();

		$this->assertSame($result, $repo->getByKey(1));
	}

	public function testInvalidCreate()
	{
		list($model, $validator, $repo) = $this->make('RepoWithPrepares');
		$validator->shouldReceive('validCreate')->andReturn(false);
		$validator->shouldReceive('errors->getMessages')->andReturn([]);

		$this->assertFalse($repo->create(array()));
	}

	public function testCreate()
	{
		list($model, $validator, $repo) = $this->make();
		$validator->shouldReceive('validCreate')->andReturn(true);
		$model->shouldReceive('newInstance')->with(['foo' => 'bar'])
			->andReturn($mock = m::mock(['save' => true]));
		$this->assertSame($mock, $repo->create(['foo' => 'bar']));
	}

	/**
	 * @expectedException RuntimeException
	 */
	public function testUpdateWhenNotExists()
	{
		list($model, $validator, $repo) = $this->make('RepoWithPrepares');
		$updateModel = new RepoTestModelStub;
		$updateModel->exists = false;

		$repo->update($updateModel, []);
	}

	public function testUpdateValidationFails()
	{
		list($model, $validator, $repo) = $this->make();
		$updateModel = new RepoTestModelStub;
		$updateModel->id = 'foo';
		$updateModel->exists = true;
		$validator->shouldReceive('replace')->with('key', 'foo');
		$validator->shouldReceive('validUpdate')->andReturn(false);
		$validator->shouldReceive('errors->getMessages')->andReturn([]);

		$this->assertFalse($repo->update($updateModel, array()));
	}

	public function testUpdate()
	{
		list($model, $validator, $repo) = $this->make();
		$updateModel = $this->makeMockModel()->makePartial();
		$updateModel->id = 'foo';
		$updateModel->exists = true;
		$updateModel->shouldReceive('fill')->once()->with(['foo' => 'bar']);
		$updateModel->shouldReceive('save')->once()->andReturn(true);
		$validator->shouldReceive('replace')->with('key', 'foo');
		$validator->shouldReceive('validUpdate')->andReturn(true);

		$this->assertTrue($repo->update($updateModel, ['foo' => 'bar']));
	}

	public function testUpdateWithKey()
	{
		list($model, $validator, $repo) = $this->make();
		$updateModel = $this->makeMockModel()->makePartial();
		$updateModel->id = 1;
		$updateModel->exists = true;
		$updateModel->shouldReceive('fill')->once()->with(['foo' => 'bar']);
		$updateModel->shouldReceive('save')->once()->andReturn(true);
		$query = $this->makeMockQuery();
		$model->shouldReceive('newQuery')->andReturn($query);
		$model->shouldReceive('getQualifiedKeyName');
		$query->shouldReceive('where->first')->andReturn($updateModel);
		$validator->shouldReceive('replace')->with('key', 1);
		$validator->shouldReceive('validUpdate')->andReturn(true);

		$this->assertTrue($repo->update(1, ['foo' => 'bar']));
	}

	public function testDelete()
	{
		list($model, $validator, $repo) = $this->make();
		$model = $this->makeMockModel();
		$model->shouldReceive('delete')->once()->andReturn(true);

		$this->assertTrue($repo->delete($model));
	}

	protected function make($class = 'RepoStub')
	{
		return [
			$m = $this->makeMockModel(),
			$v = $this->makeMockValidator(),
			$this->makeRepo($m, $v, $class),
		];
	}

	protected function makeRepo($model, $validator, $class = 'RepoStub')
	{
		return new $class($model, $validator);
	}

	public function makeMockModel($class = 'Illuminate\Database\Eloquent\Model')
	{
		$mock = m::mock($class);
		$mock->shouldReceive('getTable')->andReturn('table');
		return $mock;
	}

	public function makeMockValidator($class = 'c\Validator')
	{
		$mock = m::mock($class);
		$mock->shouldReceive('replace')->with('table', 'table');
		return $mock;
	}

	public function makeMockQuery()
	{
		return m::mock('Illuminate\Database\Eloquent\Builder');
	}
}

class RepoStub extends \c\EloquentRepository {}

class RepoWithPrepares extends \c\EloquentRepository
{
	protected function prepareQuery($query, $many)
	{
		$query->prepareQuery();
	}

	public function prepareModel($model)
	{
		$model->prepareModel();
	}

	public function preparePaginator($paginator)
	{
		$paginator->preparePaginator();
	}
}

class RepoTestModelStub extends Illuminate\Database\Eloquent\Model
{

}
