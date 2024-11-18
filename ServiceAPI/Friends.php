<?php declare(strict_types=1);
namespace openvk\ServiceAPI;

use openvk\Web\Models\Entities\{User, FriendsList};
use openvk\Web\Models\Repositories\{FriendsLists, Users};

class Friends implements Handler
{
    private $user;
    private $friendsLists;
    
    public function __construct(?User $user)
    {
        $this->user = $user;
        $this->friendsLists = new FriendsLists;
        $this->users = new Users;
    }

    function getLists(int $id, callable $resolve, callable $reject)
    {
        $lists = $this->friendsLists->getUserFriendsLists($this->user, 1, OPENVK_ROOT_CONF["openvk"]["preferences"]["maxFriendsLists"]);
        $usver = $this->users->get($id);
        
        if(!$usver) {
            $reject(1, "Invalid user");
        }

        if($id == $this->user->getId()) {
            $reject(2, "Bruh");
        }

        $ar = [];

        foreach($lists as $list) {
            if($list->hasFriend($usver)) continue;
            $ar[] = [
                "id"   => $list->getId(),
                "name" => $list->getName()
            ];
        }

        $resolve($ar);
    }

    function createFriendsList(string $name, string $color, callable $resolve, callable $reject)
    {
        if(!$name || empty($name)) {
            $reject(1, "No name");
        }

        if($this->friendsLists->getFriendsListsCount($this->user) >= OPENVK_ROOT_CONF["openvk"]["preferences"]["maxFriendsLists"]) {
            $reject(10, "Too many friends lists");
        }

        $friendsList = new FriendsList;

        $clr = ltrim($color, '#');

        $friendsList->setOwner($this->user->getId());
        $friendsList->setName($name);
        $friendsList->setColor($clr);

        $friendsList->save();
        
        $resolve([
            "name" => $friendsList->getName(),
            "id"   => $friendsList->getId(),
            "usid" => $this->user->getId()
        ]);
    }

    function addToList(int $user, int $list, callable $resolve, callable $reject)
    {
        $userg = $this->users->get($user);

        if(!$userg) {
            $reject(12, "Invalid user");
        }

        if($userg->getSubscriptionStatus($this->user) < 3) {
            $reject(12, "You don't have this user at friendlist");
        }

        $listg = $this->friendsLists->get($list);

        if(!$listg) {
            $reject(12, "Invalid list");
        }

        if($listg->getOwner()->getId() != $this->user->getId()) {
            $reject(12, "Access denied");
        }

        if($listg->hasFriend($userg)) {
            $reject(125, "Friend already in this list");
        }
        
        $listg->addFriend($userg);

        $resolve([
            "id"       => $userg->getId(),
            "color"    => $listg->getColor(),
            "thisUser" => $this->user->getId(),
            "listId"   => $listg->getId(),
            "listName" => $listg->getName(),
            "size"     => $listg->size()
        ]);
    }

    function getFriendsListsCount(int $user, callable $resolve, callable $reject)
    {
        $userg = $this->users->get($user);

        $resolve($this->friendsLists->getFriendsListsCount($userg));
    }

    function getFlistInfo(int $list, callable $resolve, callable $reject)
    {
        $listg = $this->friendsLists->get($list);

        if($listg->getOwner()->getId() != $this->user->getId()) {
            $reject(45, "You can't edit other's lists");
        }

        if(!$listg) {
            $reject(2, "Invalid list");
        }


        $resolve([
            "name"    => $listg->getName(),
            "color"   => $listg->getColor(),
            "special" => $listg->isSpecialType(),
        ]);
    }

    function editList(int $id, string $name, string $color, callable $resolve, callable $reject)
    {
        $friendsList = $this->friendsLists->get($id);

        if($friendsList->getOwner()->getId() != $this->user->getId()) {
            $reject(45, "You can't edit other's lists");
        }

        $clr = ltrim($color, '#');

        if(!empty($name)) {
            $friendsList->setName($name);
        }

        $friendsList->setColor($clr);

        $friendsList->save();

        $resolve([
            "id"   => $friendsList->getId(),
            "name" => $friendsList->getName()
        ]);
    }

    function deleteList(int $id, callable $resolve, callable $reject)
    {
        $friendsList = $this->friendsLists->get($id);

        if(!$friendsList || $friendsList->getOwner()->getId() != $this->user->getId()) {
            $reject(45, "You can't edit other's lists");
        }

        if($friendsList->isSpecialType()) {
            $reject(45, "You can't delete system's list");
        }

        foreach($friendsList->getFriends(1, 100) as $friend) {
            $friendsList->deleteFriend($friend);
        }

        $friendsList->delete();

        $resolve(["id" => $this->user->getId()]);
    }

    function deleteFromList(int $id, int $list, callable $resolve, callable $reject)
    {
        $friendsList = $this->friendsLists->get($list);

        if(!$friendsList || $friendsList->getOwner()->getId() != $this->user->getId()) {
            $reject(45, "You can't edit other's lists");
        }

        $us = $this->users->get($id);

        $friendsList->deleteFriend($us);

        $resolve([
            "newCount" => $friendsList->size()
        ]);
    }

    function getMaxListsCount(callable $resolve, callable $reject)
    {
        $resolve([
            "count" => OPENVK_ROOT_CONF["openvk"]["preferences"]["maxFriendsLists"]
        ]);
    }

    function getFriends(callable $resolve, callable $reject)
    {
        $usrs = $this->user->getFriends();

        $res = [];

        foreach($usrs as $user) {
            $res[] = [
                "name"   => $user->getCanonicalName(),
                "avatar" => $user->getAvatarUrl(),
                "id"     => $user->getId()
            ];
        }

        $resolve($res);
    }
}