<?php

use Tests\Support\Models\ExampleModel;

describe('Database', function () {
    test('model findAll returns seeded rows', function () {
        $model = new ExampleModel();
        $objects = $model->findAll();
        $this->assertCount(3, $objects);
    });

    test('soft delete leaves row in database', function () {
        $model = new ExampleModel();
        $this->setPrivateProperty($model, 'useSoftDeletes', true);
        $this->setPrivateProperty($model, 'tempUseSoftDeletes', true);

        /** @var stdClass $object */
        $object = $model->first();
        $model->delete($object->id);

        $this->assertNull($model->find($object->id));

        $result = $model->builder()->where('id', $object->id)->get()->getResult();
        $this->assertCount(1, $result);
    });
});
