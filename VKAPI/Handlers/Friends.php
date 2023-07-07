<?php declare(strict_types=1);
namespace openvk\VKAPI\Handlers;
use openvk\Web\Models\Repositories\Users as UsersRepo;
use openvk\Web\Models\Repositories\FriendsLists as FriendsRepo;
use openvk\Web\Models\Entities\{FriendsList};

final class Friends extends VKAPIRequestHandler
{
	function get(int $user_id, string $fields = "", int $offset = 0, int $count = 100): object
	{
		$i = 0;
		$offset++;
		$friends = [];

		$users = new UsersRepo;

		$this->requireUser();
		
		foreach($users->get($user_id)->getFriends($offset, $count) as $friend) {
			$friends[$i] = $friend->getId();
			$i++;
		}

		$response = $friends;

		$usersApi = new Users($this->getUser());

		if(!is_null($fields))
			$response = $usersApi->get(implode(',', $friends), $fields, 0, $count);  # FIXME

		return (object) [
			"count" => $users->get($user_id)->getFriendsCount(),
			"items" => $response
		];
	}

	function getLists(/*int $user_id*/ bool $return_system = true): object
	{
		# Нельзя получить списки другого человека
		$this->requireUser();
		$this->willExecuteWriteAction();

		$user = $this->getUser(); #(new Users)->get($user_id);

		$lists = (new FriendsRepo)->getUserFriendsLists($user, 1, OPENVK_ROOT_CONF["openvk"]["preferences"]["maxFriendsLists"]);

		$res = [
			"count" => (new FriendsRepo)->getFriendsListsCount($user),
			"items" => []
		];

		foreach($lists as $list) {
			if($list->isSpecialType() && !$return_system) continue;
			
			$res["items"][] = $list->toVkApiStruct();
		}

		return (object) $res;
	}

	function deleteList(string $list_id): int
	{
		$this->requireUser();
		$this->willExecuteWriteAction();

		$list = (new FriendsRepo)->get((int)$list_id);

		if(!$list || $list->isDeleted()) {
			$this->fail(171, "Invalid list id");
		}

		if($list->isSpecialType()) {
			$this->fail(151, "You can't delete system's list");
		}

		$list->delete();

		return 1;
	}

	function edit(int $user_id, string $list_ids): int
	{
		$this->requireUser();
		$this->willExecuteWriteAction();

		$user = (new UsersRepo)->get($user_id);

		if(!$user || $user->isDeleted() || $user->getSubscriptionStatus($this->getUser()) < 3) return 0;

		$lists = explode(",", $list_ids);

		foreach($lists as $list) {
			$listg = (new FriendsRepo)->get((int)$list);
			if(!$listg || is_null($listg) || $listg->isDeleted() || $listg->getOwner()->getId() != $this->getUser()->getId() || $listg->hasFriend($user)) continue;

			$listg->addFriend($user);
		}

		return 1;
	}

	function editList(string $list_id, string $name = "", string $color = "", string $add_user_ids = "", string $delete_user_ids = ""): int
	{
		# я хз чё user_ids должен делать
		$this->requireUser();
		$this->willExecuteWriteAction();

		$list = (new FriendsRepo)->get((int)$list_id);

		if(!empty($name)) {
			if(!$list->isSpecialType()) {
				$list->setName($name);
			}
		}

		if(!empty($color)) {
			$list->setColor($color);
		}

		$list->save();

		if(!empty($add_user_ids)) {
			$adds = explode(",", $add_user_ids);

			foreach($adds as $add) {
				$user = (new UsersRepo)->get((int)$add);

				if(!$user || is_null($user) || $user->isDeleted() || $user->getSubscriptionStatus($this->getUser()) < 3 || $list->hasFriend($user)) continue;
				
				$list->addFriend($user);
			}
		}

		if(!empty($delete_user_ids)) {
			$dels = explode(",", $delete_user_ids);

			foreach($dels as $del) {
				$user = (new UsersRepo)->get((int)$del);
				
				if(!$user || is_null($user) || $user->isDeleted() || $user->getSubscriptionStatus($this->getUser()) < 3 || $list->hasFriend($user)) continue;

				$list->deleteFriend($user);
			}
		}

		return 1;
	}

	function add(string $user_id): int
	{
		$this->requireUser();
        $this->willExecuteWriteAction();

		$users = new UsersRepo;
		$user  = $users->get(intval($user_id));
		
		if(is_null($user)) {
			$this->fail(177, "Cannot add this user to friends as user not found");
		} else if($user->getId() == $this->getUser()->getId()) {
			$this->fail(174, "Cannot add user himself as friend");
		}

		switch($user->getSubscriptionStatus($this->getUser())) {
			case 0:
				$user->toggleSubscription($this->getUser());
				return 1;

			case 1:
				$user->toggleSubscription($this->getUser());
				return 2;

			case 3:
				return 2;
			
			default:
				return 1;
		}
	}

	function delete(string $user_id): int
	{
		$this->requireUser();
        $this->willExecuteWriteAction();

		$users = new UsersRepo;

		$user = $users->get(intval($user_id));

		switch($user->getSubscriptionStatus($this->getUser())) {
			case 3:
				$user->toggleSubscription($this->getUser());
				return 1;
			
			default:
				$this->fail(15, "Access denied: No friend or friend request found.");
		}
	}

	function areFriends(string $user_ids): array
	{
		$this->requireUser();

		$users = new UsersRepo;

		$friends = explode(',', $user_ids);

		$response = [];

		for($i=0; $i < sizeof($friends); $i++) { 
			$friend = $users->get(intval($friends[$i]));

			$response[] = (object)[
				"friend_status" => $friend->getSubscriptionStatus($this->getUser()),
				"user_id" 		=> $friend->getId()
			];
		}

		return $response;
	}

	function getRequests(string $fields = "", int $offset = 0, int $count = 100, int $extended = 0): object
	{
		if ($count >= 1000)
			$this->fail(100, "One of the required parameters was not passed or is invalid.");

		$this->requireUser();

		$i = 0;
		$offset++;
		$followers = [];

		foreach($this->getUser()->getFollowers($offset, $count) as $follower) {
			$followers[$i] = $follower->getId();
			$i++;
		}

		$response = $followers;
		$usersApi = new Users($this->getUser());

		$response = $usersApi->get(implode(',', $followers), $fields, 0, $count);

		foreach($response as $user)
			$user->user_id = $user->id;

		return (object) [
			"count" => $this->getUser()->getFollowersCount(),
			"items" => $response
		];
	}
                                   # <- без
	function addList(string $name, string $color = "", string $user_ids = "")
	{
		$this->requireUser();
		$this->willExecuteWriteAction();

		if((new FriendsRepo)->getFriendsListsCount($this->getUser()) >= OPENVK_ROOT_CONF["openvk"]["preferences"]["maxFriendsLists"]) {
            $this->fail(173, "Reached the maximum number of lists");
        }

		$friendsList = new FriendsList;

        $friendsList->setOwner($this->getUser()->getId());
        $friendsList->setName($name);

		if(!empty($color)) {
			$friendsList->setColor($color);
		}

        $friendsList->save();

		if(!empty($user_ids)) {
			$friends = explode(",", $user_ids);

			foreach($friends as $fr) {
				$userg = (new UsersRepo)->get((int)$fr);
	
				if(!$userg || is_null($userg) || $user->isDeleted() || $userg->getSubscriptionStatus($this->getUser()) < 3) continue;
	
				$friendsList->addFriend($userg);
			}
		}

		return $friendsList->getId();
	}
}