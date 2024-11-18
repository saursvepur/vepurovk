<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use openvk\Web\Models\RowModel;

class Fave extends RowModel
{
    protected $tableName = "faves";

    function getTarget()
    {
        $entityClassName = $this->getRecord()->model;
        $repoClassName   = str_replace("Entities", "Repositories", $entityClassName) . "s";
        $entity          = (new $repoClassName)->get($this->getRecord()->target);
        
        return $entity;
    }
}
