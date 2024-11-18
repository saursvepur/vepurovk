<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use Chandler\Database\DatabaseConnection;
use openvk\Web\Models\Entities\{Fave, User};

class Faves
{
    private $context;
    private $faves;
    private $cats;
    
    function __construct()
    {
        $this->context = DatabaseConnection::i()->getContext();
        $this->faves   = $this->context->table("faves");
    }
    
    function get(int $id): ?Fave
    {
        $fave = $this->faves->get($id);
        if(!$fave)
            return NULL;
        
        return new Fave($fave);
    }
    
    function getFavesByUser(User $user, int $page = 1, ?int $perPage = NULL, string $model = "openvk\\Web\\Models\\Repositories\\Posts"): \Traversable
    {
        $perPage = $perPage ?? 10;

        foreach($this->faves->where("owner", $user->getId())->where("deleted", 0)->where("model", $model)->page($page, $perPage)->order("created DESC") as $fave)
            yield new Fave($fave);
    }

    function getFavesCountByUser(User $user, string $model = "openvk\\Web\\Models\\Repositories\\Posts"): int
    {
        return sizeof($this->faves->where("owner", $user->getId())->where(["deleted" => 0, "model" => $model]));
    }

    function getFavesByUserAndTarget(User $user, string $model, int $target)
    {
        $searchData = $this->faves->where("owner", $user->getId())->where("model", $model)->where("target", $target)->fetch();
       
        return is_null($searchData) ? NULL : new Fave($searchData);
    }
}
