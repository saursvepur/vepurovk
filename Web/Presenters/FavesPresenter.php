<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Entities\Fave;
use openvk\Web\Models\Repositories\{Faves, Users, Photos, Videos, Notes, Clubs, Posts};
use openvk\Web\Models\Entities\Notifications\GiftNotification;

final class FavesPresenter extends OpenVKPresenter
{
    private $faves;
    protected $presenterName = "faves";

    private $models = [
        "users"   => "openvk\\Web\\Models\\Entities\\User",
        "photos"  => "openvk\\Web\\Models\\Entities\\Photo",
        "videos"  => "openvk\\Web\\Models\\Entities\\Video",
        "notes"   => "openvk\\Web\\Models\\Entities\\Note",
        "clubs"   => "openvk\\Web\\Models\\Entities\\Club",
        "posts"   => "openvk\\Web\\Models\\Entities\\Post",
    ];

    private function getEntityByModel(string $model, int $id)
    {
        switch($model) {
            case $this->models["users"]:
                $result = (new Users)->get($id);
                break;
            case $this->models["photos"]:
                $result = (new Photos)->get($id);
                break;
            case $this->models["videos"]:
                $result = (new Videos)->get($id);
                break;
            case $this->models["notes"]:
                $result = (new Notes)->get($id);
                break;
            case $this->models["clubs"]:
                $result = (new Clubs)->get($id);
                break;
            case $this->models["posts"]:
                $result = (new Posts)->get($id);
                break;
            default:
                $this->notFound();
        }

        return $result;
    }
    
    function __construct(Faves $faves)
    {
        $this->faves = $faves;
    }

    function renderIndex()
    {
        $this->assertUserLoggedIn();

        $type = $this->queryParam("type") ?? "users";
        $model = $this->models[$type];
        $page = (int)($this->queryParam("p") ?? 1);

        if(!$model)
            $this->notFound();

        $iterator = $this->faves->getFavesByUser($this->user->identity, $page, 10, $model);

        $this->template->iterator = $iterator;
        $this->template->count = $this->faves->getFavesCountByUser($this->user->identity, $model);
        $this->template->page = $page;
        $this->template->type = $type;
    }

    function renderAdd()
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();

        if($_SERVER["REQUEST_METHOD"] !== "POST")
            $this->redirect("/fave");
        
        $type = $this->postParam("model");

        if(!$this->models[$type])
            $this->returnJson(["error" => "Invalid model"]);
        
        $obj = $this->getEntityByModel($this->models[$type], (int)$this->postParam("target"));

        if(!$obj || $obj->isDeleted())
            $this->returnJson(["error" => "Invalid object"]);
        
        $maybeFave = $this->faves->getFavesByUserAndTarget($this->user->identity, $this->models[$type], (int)$this->postParam("target"));
        $action = "faved";

        if(is_null($maybeFave)) {
            $fave = new Fave;
            $fave->setOwner($this->user->id);
            $fave->setModel($this->models[$type]);
            $fave->setTarget((int)$this->postParam("target"));
            $fave->setCreated(time());
    
            $fave->save();
        } else {
            if($maybeFave->isDeleted()) {
                $maybeFave->setCreated(time());
                $maybeFave->undelete();
            } else {
                $maybeFave->setCreated(time());
                $maybeFave->delete();
                $action = "unfaved";
            }
        }
        
        $this->returnJson(["error" => "no",
                            "action" => $action]);
    }
}
