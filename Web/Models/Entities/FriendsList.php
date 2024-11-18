<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use Nette\Database\Table\ActiveRow;
use openvk\Web\Models\Entities\User;
use openvk\Web\Models\Repositories\{FriendsLists, Users};
use openvk\Web\Models\RowModel;
use Chandler\Database\DatabaseConnection;

class FriendsList extends RowModel
{
    protected $tableName    = "friendslists";
    protected $relTableName = "friendslists_relations";

    protected $specialNames = [
        16  => "_f_best_friends",
        32  => "_f_nearfriends",
        64  => "_f_collegues",
    ];

    private $relations;

    function __construct(?ActiveRow $ar = NULL)
    {
        parent::__construct($ar);
        
        $this->relations = DatabaseConnection::i()->getContext()->table($this->relTableName);
    }

    function getFriends($page, $perPage = 6): \Traversable
    {
        $friends = $this->getRecord()->related("$this->relTableName.friendslist")->page($page, $perPage ?? OPENVK_DEFAULT_PER_PAGE);

        foreach($friends as $friend) {
            $friendm = $friend->ref("profiles", "friend");
            if(!$friendm)
                continue;
            
            yield new User($friendm);
        }
    }

    function getOwner()
    {
        $uswer = (new Users)->get($this->getRecord()->owner);

        return $uswer;
    }

    function isSpecialType()
    {
        if($this->getRecord()->special_type != 0) {
            return true;
        } else {
            return false;
        }
    }

    function getName()
    {
        $special = $this->getRecord()->special_type;

        if(!$this->isSpecialType())
            return $this->getRecord()->name;
        
        $sName = $this->specialNames[$special];
        if(!$sName)
            return $this->getRecord()->name;
            
        if($sName[0] === "_")
            $sName = tr(substr($sName, 1));
        
        return $sName;
    }

    function addFriend(User $user): void
    {
        $thisUser = (new Users)->get($this->getOwner()->getId());

        if(!$this->hasFriend($user) && $user->getSubscriptionStatus($thisUser) == 3) {
            $this->relations->insert([
                "friendslist" => $this->getId(),
                "friend"      => $user->getId(),
            ]);
        }
    }

    function deleteFriend(User $user): void
    {
        $this->relations->where([
            "friendslist" => $this->getId(),
            "friend"      => $user->getId(),
        ])->delete();
    }

    function hasFriend(User $user)
    {
        $has = $this->relations->where([
            "friendslist" => $this->getId(),
            "friend"        => $user->getId(),
        ])->fetch();

        return !is_null($has);
    }

    function getColor()
    {
        return $this->getRecord()->color;
    }

    function size(): int
    {
        return sizeof($this->getRecord()->related("friendslists_relations.friendslist"));
    }

    function toVkApiStruct(): object
    {
        $res = (object) [];

        $res->name   = $this->getName();
        $res->id     = $this->getId();
        $res->color  = $this->getColor();

        return $res;
    }
}