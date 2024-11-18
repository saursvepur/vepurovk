<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use Chandler\Database\DatabaseConnection;
use Nette\Database\Table\ActiveRow;
use openvk\Web\Models\Entities\{FriendsList, User};

class FriendsLists
{
    private $context;
    private $friendslists;
    
    function __construct()
    {
        $this->context = DatabaseConnection::i()->getContext();
        $this->friendslists   = $this->context->table("friendslists");
    }

    private function toFriendsList(?ActiveRow $ar): ?FriendsList
    {
        return is_null($ar) ? NULL : new FriendsList($ar);
    }

    function get(int $id): ?FriendsList
    {
        return $this->toFriendsList($this->friendslists->get($id));
    }

    function getUserFriendsLists(User $user, int $page = 1, ?int $perPage = NULL): \Traversable
    {
        $perPage = $perPage ?? OPENVK_DEFAULT_PER_PAGE;
        $flists  = $this->friendslists->where("owner", $user->getId())->where("deleted", false);
        foreach($flists->page($page, $perPage) as $flist)
            yield new FriendsList($flist);
    }

    function getFriendsListsCount(User $user): int
    {
        return sizeof($this->friendslists->where("owner", $user->getId())->where("deleted", false));
    }
}