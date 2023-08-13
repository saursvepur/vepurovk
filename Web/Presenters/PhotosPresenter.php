<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Entities\{Club, Photo, Album};
use openvk\Web\Models\Repositories\{Photos, Albums, Users, Clubs, User};
use Nette\InvalidStateException as ISE;

final class PhotosPresenter extends OpenVKPresenter
{
    private $users;
    private $photos;
    private $albums;
    protected $presenterName = "photos";

    function __construct(Photos $photos, Albums $albums, Users $users)
    {
        $this->users  = $users;
        $this->photos = $photos;
        $this->albums = $albums;
        
        parent::__construct();
    }
    
    function renderAlbumList(int $owner): void
    {
        if($owner > 0) {
            $user = $this->users->get($owner);
            if(!$user) $this->notFound();
            if (!$user->getPrivacyPermission('photos.read', $this->user->identity ?? NULL))
                $this->flashFail("err", tr("forbidden"), tr("forbidden_comment"));
            $this->template->albums  = $this->albums->getUserAlbums($user, (int)($this->queryParam("p") ?? 1));
            $this->template->count   = $this->albums->getUserAlbumsCount($user);
            $this->template->owner   = $user;
            $this->template->canEdit = false;
            if(!is_null($this->user))
                $this->template->canEdit = $this->user->id === $user->getId();
        } else {
            $club = (new Clubs)->get(abs($owner));
            if(!$club) $this->notFound();
            $this->template->albums  = $this->albums->getClubAlbums($club, (int)($this->queryParam("p") ?? 1));
            $this->template->count   = $this->albums->getClubAlbumsCount($club);
            $this->template->owner   = $club;
            $this->template->canEdit = false;
            if(!is_null($this->user))
                $this->template->canEdit = $club->canBeModifiedBy($this->user->identity);
        }
        
        $this->template->paginatorConf = (object) [
            "count"   => $this->template->count,
            "page"    => $this->queryParam("p") ?? 1,
            "amount"  => NULL,
            "perPage" => OPENVK_DEFAULT_PER_PAGE,
        ];
    }
    
    function renderCreateAlbum(): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        if(!is_null($gpid = $this->queryParam("gpid"))) {
            $club = (new Clubs)->get((int) $gpid);
            if(!$club->canBeModifiedBy($this->user->identity))
                $this->notFound();
            
            $this->template->club = $club;
        }
        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            if(empty($this->postParam("name")) || mb_strlen(trim($this->postParam("name"))) === 0)
                $this->flashFail("err", tr("error"), tr("error_segmentation")); 
            else if(strlen($this->postParam("name")) > 36)
                $this->flashFail("err", tr("error"), tr("error_data_too_big", "name", 36, "bytes")); 

            $album = new Album;
            $album->setOwner(isset($club) ? $club->getId() * -1 : $this->user->id);
            $album->setName($this->postParam("name"));
            $album->setDescription($this->postParam("desc"));
            $album->setCreated(time());
            $album->save();
            
            if(isset($club))
                $this->redirect("/album-" . $album->getOwner()->getId() . "_" . $album->getId());
            else
                $this->redirect("/album" . $album->getOwner()->getId() . "_" . $album->getId());
        }
    }
    
    function renderEditAlbum(int $owner, int $id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $album = $this->albums->get($id);
        if(!$album) $this->notFound();
        if($album->getPrettyId() !== $owner . "_" . $id || $album->isDeleted()) $this->notFound();
        if(is_null($this->user) || !$album->canBeModifiedBy($this->user->identity) || $album->isDeleted())
            $this->flashFail("err", "Ошибка доступа", "Недостаточно прав для модификации данного ресурса.");
        $this->template->album = $album;
        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            if(strlen($this->postParam("name")) > 36)
                $this->flashFail("err", tr("error"), tr("error_data_too_big", "name", 36, "bytes"));
            
                $album->setName((empty($this->postParam("name")) || mb_strlen(trim($this->postParam("name"))) === 0) ? $album->getName() : $this->postParam("name"));
            $album->setDescription(empty($this->postParam("desc")) ? NULL : $this->postParam("desc"));
            $album->setEdited(time());
            $album->save();
            
            $this->flash("succ", "Изменения сохранены", "Новые данные приняты.");
        }
    }
    
    function renderDeleteAlbum(int $owner, int $id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        $this->assertNoCSRF();
        
        $album = $this->albums->get($id);
        if(!$album) $this->notFound();
        if($album->getPrettyId() !== $owner . "_" . $id || $album->isDeleted()) $this->notFound();
        if(is_null($this->user) || !$album->canBeModifiedBy($this->user->identity))
            $this->flashFail("err", "Ошибка доступа", "Недостаточно прав для модификации данного ресурса.");
        
        $name  = $album->getName();
        $owner = $album->getOwner();
        $album->delete();

        $this->flash("succ", "Альбом удалён", "Альбом $name был успешно удалён.");
        $this->redirect("/albums" . ($owner instanceof Club ? "-" : "") . $owner->getId());
    }
    
    function renderAlbum(int $owner, int $id): void
    {
        $album = $this->albums->get($id);
        if(!$album) $this->notFound();
        if($album->getPrettyId() !== $owner . "_" . $id || $album->isDeleted())
            $this->notFound();
        
        if($owner > 0 /* bc we currently don't have perms for clubs */) {
            $ownerObject = (new Users)->get($owner);
            if(!$ownerObject->getPrivacyPermission('photos.read', $this->user->identity ?? NULL))
                $this->flashFail("err", tr("forbidden"), tr("forbidden_comment"));
        }
        
        $this->template->album  = $album;
        $this->template->photos = iterator_to_array( $album->getPhotos( (int) ($this->queryParam("p") ?? 1), 20) );
        $this->template->paginatorConf = (object) [
            "count"   => $album->getPhotosCount(),
            "page"    => $this->queryParam("p") ?? 1,
            "amount"  => sizeof($this->template->photos),
            "perPage" => 20,
            "atBottom" => true
        ];
    }
    
    function renderPhoto(int $ownerId, int $photoId): void
    {
        $photo = $this->photos->getByOwnerAndVID($ownerId, $photoId);
        if(!$photo || $photo->isDeleted()) $this->notFound();

        if ($photo->getOwner() instanceof User && $photo->getOwner()->isServiceAccount())
            $this->notFound();
        
        if(!is_null($this->queryParam("from"))) {
            if(preg_match("%^album([0-9]++)$%", $this->queryParam("from"), $matches) === 1) {
                $album = $this->albums->get((int) $matches[1]);
                if($album)
                    if($album->hasPhoto($photo) && !$album->isDeleted())
                        $this->template->album = $album;
            }
        }
        
        $this->template->photo    = $photo;
        $this->template->cCount   = $photo->getCommentsCount();
        $this->template->cPage    = (int) ($this->queryParam("p") ?? 1);
        $this->template->comments = iterator_to_array($photo->getComments($this->template->cPage));
    }
    
    function renderAbsolutePhoto($id): void
    {
        $id    = (int) base_convert((string) $id, 32, 10);
        $photo = $this->photos->get($id);
        if(!$photo || $photo->isDeleted())
            $this->notFound();
        
        $this->template->_template = "Photos/Photo.xml";
        $this->renderPhoto($photo->getOwner(true)->getId(), $photo->getVirtualId());
    }
    
    function renderThumbnail($id, $size): void
    {
        $photo = $this->photos->get($id);
        if(!$photo || $photo->isDeleted())
            $this->notFound();
        
        if(!$photo->forceSize($size))
            chandler_http_panic(588, "Gone", "This thumbnail cannot be generated due to server misconfiguration");
        
        $this->redirect($photo->getURLBySizeId($size), 8);
    }
    
    function renderEditPhoto(int $ownerId, int $photoId): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $photo = $this->photos->getByOwnerAndVID($ownerId, $photoId);
        if(!$photo) $this->notFound();
        if(is_null($this->user) || $this->user->id != $ownerId)
            $this->flashFail("err", "Ошибка доступа", "Недостаточно прав для модификации данного ресурса.");
        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            $photo->setDescription(empty($this->postParam("desc")) ? NULL : $this->postParam("desc"));
            $photo->save();
            
            $this->flash("succ", "Изменения сохранены", "Обновлённое описание появится на странице с фоткой.");
            $this->redirect("/photo" . $photo->getPrettyId());
        } 
        
        $this->template->photo = $photo;
    }
    
    function renderUploadPhoto(): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        if(is_null($this->queryParam("album")))
            $this->returnJson([
                "success"    => 0,
                "error"      => "unknown_deleted",
            ]);
        
        [$owner, $id] = explode("_", $this->queryParam("album"));
        $album = $this->albums->get((int) $id);
        if(!$album)
            $this->returnJson([
                "success" => 0,
                "error"   => "select_album"
            ]);
        if(is_null($this->user) || !$album->canBeModifiedBy($this->user->identity))
            $this->returnJson([
                "success" => 0,
                "error"   => "no_rights"
            ]);
        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            if(!isset($_FILES))
                $this->returnJson([
                    "success" => 0,
                    "error"   => "no_photo"
                ]);
            
            $size = sizeof($_FILES);

            for($i = 0; $i < $size; $i++ ) {
                if(is_null($_FILES["blob_$i"])) continue;
                
                try {
                    $photo = new Photo;
                    $photo->setOwner($this->user->id);
                    $photo->setDescription($this->postParam("desc_$i"));
                    $photo->setFile($_FILES["blob_$i"]);
                    $photo->setCreated(time());
                    $photo->save();
                            
                    $album->addPhoto($photo);
                    $album->setEdited(time());
                    $album->save();
                } catch(ISE $ex) {
                    $name = $album->getName();
                    $this->returnJson([
                        "success"    => 0,
                        "error"      => "unknown_album",
                    ]);
                }
            }

            $this->returnJson([
                "success"     => 1,
                "success_msg" => "succefully_uploaded"
            ]);
        } else {
            $this->template->album = $album;
        }
    }
    
    function renderUnlinkPhoto(int $owner, int $albumId, int $photoId): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $album = $this->albums->get($albumId);
        $photo = $this->photos->get($photoId);
        if(!$album || !$photo) $this->returnJson(["success" => 0, "error" => "not_found"]);

        if(!$album->hasPhoto($photo)) $this->returnJson(["success" => 0, "error" => "not_found"]);

        if(is_null($this->user) || !$album->canBeModifiedBy($this->user->identity))
            $this->returnJson([
                "success"     => 0,
                "error" => "no_rights_error"
            ]);
        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            $this->assertNoCSRF();
            $album->removePhoto($photo);
            $album->setEdited(time());
            $album->save();

            if($this->postParam("fully_delete") === "true") {
                $photo->isolate();
                $photo->delete();
            }
            
            $this->returnJson([
                "success"     => 1,
                "success_msg" => "succefully_deleted",
                "newCount"    => $album->size()
            ]);
        }
    }
    
    function renderDeletePhoto(int $ownerId, int $photoId): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        $this->assertNoCSRF();
        
        $photo = $this->photos->getByOwnerAndVID($ownerId, $photoId);
        if(!$photo) $this->notFound();
        if(is_null($this->user) || $this->user->id != $ownerId)
            $this->flashFail("err", "Ошибка доступа", "Недостаточно прав для модификации данного ресурса.");
        
        $photo->isolate();
        $photo->delete();
        
        $this->flash("succ", "Фотография удалена", "Эта фотография была успешно удалена.");
        $this->redirect("/id0");
    }
}