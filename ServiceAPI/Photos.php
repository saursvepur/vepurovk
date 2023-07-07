<?php declare(strict_types=1);
namespace openvk\ServiceAPI;

use openvk\Web\Models\Entities\User;
use openvk\Web\Models\Repositories\Photos as PhotosRepo;
use openvk\Web\Models\Repositories\Albums;

class Photos implements Handler
{
    protected $user;
    protected $albums;
    protected $photos;
    
    function __construct(?User $user)
    {
        $this->user = $user;
        $this->albums = new Albums;
        $this->photos = new PhotosRepo;
    }

    function getAlbumInfo(int $id, callable $resolve, callable $reject)
    {
        $album = $this->albums->get($id);

        if(!$album) {
            $reject(12, "Invalid album");
        }

        if($album->getOwner() instanceof User > 0 && $album->getOwner()->getId() != $this->user->getId() || $album->getOwner() instanceof openvk\Web\Models\Entities\Club && !$album->getOwner()->canBeModifiedBy($this->user)) {
            $reject(14, "No rights");
        }

        if($album->isCreatedBySystem()) {
            $reject(14, "You can't edit system album");
        }

        $resolve(
            [
                "name"        => $album->getName(),
                "description" => $album->getDescription(),
                "owner"       => $album->getOwner() instanceof User ? $album->getOwner()->getId() : $album->getOwner()->getId() * -1,
                "id"          => $album->getId()
            ]
            );
    }

    function editAlbum(int $id, string $name, string $desc, callable $resolve, callable $reject)
    {
        $album = $this->albums->get($id);

        if(!$album) {
            $reject(12, "Invalid album");
        }

        if($album->getOwner()->getId() > 0 && $album->getOwner()->getId() != $this->user->getId()) {
            $reject(14, "No rights");
        }

        if($album->isCreatedBySystem()) {
            $reject(14, "You can't edit system album");
        }

        if(!$name || empty($name)) {
            $reject(1, "No name");
        }

        $album->setName($name);
        $album->setDescription($desc);

        $album->save();

        $resolve([
            "success" => 1,
            "name"    => $album->getName(),
            "desc"    => $album->getDescription()
        ]);
    }
}