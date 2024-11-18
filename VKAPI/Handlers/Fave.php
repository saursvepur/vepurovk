<?php declare(strict_types=1);
namespace openvk\VKAPI\Handlers;
use openvk\Web\Models\Repositories\{Faves, Users, Clubs};
use openvk\Web\Models\Entities\Fave as Favee;

final class Fave extends VKAPIRequestHandler
{
    function addPage(int $user_id = 0, int $group_id = 0)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        if($user_id != 0 && $group_id != 0 || $user_id == 0 && $group_id == 0)
            $this->fail(100, "One of the parameters specified was missing or invalid: You should provide user_id or group_id");
        
        if($user_id != 0) {
            $object = (new Users)->get($user_id);

            if(!$object || $object->isDeleted())
                $this->fail(25, "Invalid user");
            
            if($object->getId() == $this->getUser()->getId())
                $this->fail(52, "Error: can't fave yourself");

            $model  = "openvk\\Web\\Models\\Entities\\User";
            $id     = $user_id;
        } else {
            $object = (new Clubs)->get(abs($group_id)); # без минуса

            if(!$object || $object->isDeleted())
                $this->fail(25, "Invalid club");

            if($object->getOwner()->getId() == $this->getUser()->getId())
                $this->fail(52, "Error: can't fave your club");
            
            $model  = "openvk\\Web\\Models\\Entities\\Club";
            $id     = $group_id;
        }

        $maybeFave = (new Faves)->getFavesByUserAndTarget($this->getUser(), $model, $id);

        if(is_null($maybeFave)) {
            $fave = new Favee;
            $fave->setOwner($this->getUser()->getId());
            $fave->setModel($model);
            $fave->setTarget($id);
            $fave->setCreated(time());
    
            $fave->save();
        } else {
            if($maybeFave->isDeleted()) {
                $maybeFave->setCreated(time());
                $maybeFave->undelete();
            } else {
                $this->fail(50, "User or club already faved");
            }
        }

        return 1;
    }

    function addPost(int $owner_id, int $id)
    {

    }

    function addTag(string $name, string $position)
    {

    }

    function addVideo(int $owner_id, int $id)
    {

    }

    function editTag(int $id, string $name)
    {

    }

    function get(int $offset = 0, int $count = 10, int $extended = 0, string $item_type = "post", int $tag_id = 0, string $fields = "")
    {

    }
}