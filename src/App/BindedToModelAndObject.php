<?php

namespace InWeb\Media;

use App\Exceptions\ModelIsNotBinded;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait BindedToModelAndObject
 * @package App\Traits
 * @property string|null model
 * @property int object_id
 * @property \App\Models\Entity|null object
 */
trait BindedToModelAndObject
{
    public function object()
    {
        return $this->morphTo(null, 'model', 'object_id');
    }

//    /**
//     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
//     * @throws ModelIsNotBinded
//     */
//    public function object()
//    {
//        if (! $this->model)
//            throw new ModelIsNotBinded('Param is without binded model');
//
//        return $this->belongsTo($this->model, 'object_id');
//    }

//    public function getObjectAttribute()
//    {
//        if (! $this->binded())
//            return null;
//
//        return $this->object()->withoutGlobalScopes()->getResults();
//    }

    public function binded()
    {
        return $this->bindedToModel() or $this->bindedToObject();
    }

    public function bindedToModel()
    {
        return $this->getMorphClass();
    }

    public function bindedToObject()
    {
        return $this->model != '' and $this->object_id;
    }

    public function validBinded()
    {
        return ($this->bindedToModel() and class_exists($this->model)) and
            (! $this->bindedToObject() or ($this->bindedToObject() and $this->object != null));
    }

    /**
     * @param |int $object
     * @param string|null $model
     * @return static
     * @throws ModelIsNotBinded
     */
    public function associateWith($object, $model = null)
    {
        if ($object instanceof Model) {
            $this->model = get_class($object);
        } else if ($model) {
            $this->model = $model;
        } else if (! $this->bindedToModel()) {
            throw new ModelIsNotBinded('Can not find model when binding object from int key');
        }

        $this->object()->associate($object);

        return $this;
    }
}
