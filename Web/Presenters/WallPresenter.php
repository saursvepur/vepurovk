<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Exceptions\TooMuchOptionsException;
use openvk\Web\Models\Entities\{Poll, Post, Photo, Video, Club, User};
use openvk\Web\Models\Entities\Notifications\{MentionNotification, NewSuggestedPostsNotification, RepostNotification, WallPostNotification, PostAcceptedNotification};
use openvk\Web\Models\Repositories\{Posts, Users, Clubs, Albums, Notes, Comments, Videos, Photos, Audios};
use Chandler\Database\DatabaseConnection;
use Nette\InvalidStateException as ISE;
use Bhaktaraz\RSSGenerator\Item;
use Bhaktaraz\RSSGenerator\Feed;
use Bhaktaraz\RSSGenerator\Channel;

final class WallPresenter extends OpenVKPresenter
{
    private $posts;
    
    function __construct(Posts $posts)
    {
        $this->posts = $posts;
        
        parent::__construct();
    }
    
    private function logPostView(Post $post, int $wall): void
    {
        if(is_null($this->user))
            return;
        
        $this->logEvent("postView", [
            "profile"    => $this->user->identity->getId(),
            "post"       => $post->getId(),
            "owner"      => abs($wall),
            "group"      => $wall < 0,
            "subscribed" => $wall < 0 ? $post->getOwner()->getSubscriptionStatus($this->user->identity) : false,
        ]);
    }
    
    private function logPostsViewed(array &$posts, int $wall): void
    {
        $x = array_values($posts); # clone array (otherwise Nette DB objects will become kinda gay)
        
        foreach($x as $post)
            $this->logPostView($post, $wall);
    }
    
    function renderWall(int $user, bool $embedded = false): void
    {
        $owner = ($user < 0 ? (new Clubs) : (new Users))->get(abs($user));
        if ($owner instanceof User && $owner->isServiceAccount())
            $this->flashFail("err", tr("error"), tr("forbidden"));

        if(!$owner->canBeViewedBy($this->user->identity)) {
            $this->flashFail("err", tr("error"), tr("forbidden"));
            $this->redirect("/");
        }

        if(is_null($this->user)) {
            $canPost = false;
        } else if($user > 0) {
            if(!$owner->isBanned())
                $canPost = $owner->getPrivacyPermission("wall.write", $this->user->identity);
            else
                $this->flashFail("err", tr("error"), tr("forbidden"));
        } else if($user < 0) {
            if($owner->canBeModifiedBy($this->user->identity))
                $canPost = true;
            else
                $canPost = $owner->canPost();
        } else {
            $canPost = false;
        }

        if($owner->isDeleted()) {
            $this->notFound();
        }
        
        if ($embedded == true) $this->template->_template = "components/wall.xml";
        $this->template->oObj = $owner;
        if($user < 0)
            $this->template->club = $owner;

        $iterator = NULL;
        $count = 0;
        $type = $this->queryParam("type") ?? "all";

        switch($type) {
            default:
            case "all":
                $iterator = $this->posts->getPostsFromUsersWall($user, (int) ($_GET["p"] ?? 1));
                $count = $this->posts->getPostCountOnUserWall($user);
                break;
            case "owners":
                $iterator = $this->posts->getOwnersPostsFromWall($user, (int) ($_GET["p"] ?? 1));
                $count = $this->posts->getOwnersCountOnUserWall($user);
                break;
            case "others":
                $iterator = $this->posts->getOthersPostsFromWall($user, (int) ($_GET["p"] ?? 1));
                $count = $this->posts->getOthersCountOnUserWall($user);
                break;
        }
        
        $this->template->owner   = $user;
        $this->template->canPost = $canPost;
        $this->template->count   = $count;
        $this->template->type    = $type;
        $this->template->posts   = iterator_to_array($iterator);
        $this->template->paginatorConf = (object) [
            "count"   => $this->template->count,
            "page"    => (int) ($_GET["p"] ?? 1),
            "amount"  => sizeof($this->template->posts),
            "perPage" => OPENVK_DEFAULT_PER_PAGE,
        ];

        
        $this->logPostsViewed($this->template->posts, $user);
    }

    function renderWallEmbedded(int $user): void
    {
        $this->renderWall($user, true);
    }

    function renderRSS(int $user): void
    {
        $owner = ($user < 0 ? (new Clubs) : (new Users))->get(abs($user));
        if(is_null($this->user)) {
            $canPost = false;
        } else if($user > 0) {
            if(!$owner->isBanned())
                $canPost = $owner->getPrivacyPermission("wall.write", $this->user->identity);
            else
                $this->flashFail("err", tr("error"), tr("forbidden"));
        } else if($user < 0) {
            if($owner->canBeModifiedBy($this->user->identity) && $owner->canBeViewedBy($this->user->identity))
                $canPost = true;
            else
                $canPost = $owner->canPost();
        } else {
            $canPost = false;
        }

        $posts = iterator_to_array($this->posts->getPostsFromUsersWall($user));

        $feed = new Feed();

        $channel = new Channel();
        $channel->title($owner->getCanonicalName() . " — " . OPENVK_ROOT_CONF['openvk']['appearance']['name'])->url(ovk_scheme(true) . $_SERVER["HTTP_HOST"])->appendTo($feed);

        foreach($posts as $post) {
            $item = new Item();
            $item
                ->title($post->getOwner()->getCanonicalName())
                ->description($post->getText())
                ->url(ovk_scheme(true).$_SERVER["HTTP_HOST"]."/wall{$post->getPrettyId()}")
                ->pubDate($post->getPublicationTime()->timestamp())
                ->appendTo($channel);
        }

        header("Content-Type: application/rss+xml");
        exit($feed);
    }
    
    function renderFeed(): void
    {
        $this->assertUserLoggedIn();
        
        $id    = $this->user->id;
        $subs  = DatabaseConnection::i()
                 ->getContext()
                 ->table("subscriptions")
                 ->where("follower", $id);
        $ids   = array_map(function($rel) {
            return $rel->target * ($rel->model === "openvk\Web\Models\Entities\User" ? 1 : -1);
        }, iterator_to_array($subs));
        $ids[] = $this->user->id;
        
        $perPage = min((int) ($_GET["posts"] ?? OPENVK_DEFAULT_PER_PAGE), 50);
        $posts   = DatabaseConnection::i()
                   ->getContext()
                   ->table("posts")
                   ->select("id")
                   ->where("wall IN (?)", $ids)
                   ->where("deleted", 0)
                   ->where("suggested", 0)
                   ->order("created DESC");
        $this->template->paginatorConf = (object) [
            "count"   => sizeof($posts),
            "page"    => (int) ($_GET["p"] ?? 1),
            "amount"  => sizeof($posts->page((int) ($_GET["p"] ?? 1), $perPage)),
            "perPage" => $perPage,
        ];
        $this->template->posts = [];
        foreach($posts->page((int) ($_GET["p"] ?? 1), $perPage) as $post)
            $this->template->posts[] = $this->posts->get($post->id);
    }
    
    function renderGlobalFeed(): void
    {
        $this->assertUserLoggedIn();
        
        $page  = (int) ($_GET["p"] ?? 1);
        $pPage = min((int) ($_GET["posts"] ?? OPENVK_DEFAULT_PER_PAGE), 50);

        $queryBase = "FROM `posts` LEFT JOIN `groups` ON GREATEST(`posts`.`wall`, 0) = 0 AND `groups`.`id` = ABS(`posts`.`wall`) WHERE (`groups`.`hide_from_global_feed` = 0 OR `groups`.`name` IS NULL) AND `posts`.`deleted` = 0 AND `posts`.`suggested` = 0";

        if($this->user->identity->getNsfwTolerance() === User::NSFW_INTOLERANT)
            $queryBase .= " AND `nsfw` = 0";

        if(($ignoredCount = $this->user->identity->getIgnoredSourcesCount()) > 0) {
            $sources = implode("', '", $this->user->identity->getIgnoredSources(1, $ignoredCount, true));

            $queryBase .= " AND `posts`.`wall` NOT IN ('$sources')";
        }    

        $posts = DatabaseConnection::i()->getConnection()->query("SELECT `posts`.`id` " . $queryBase . " ORDER BY `created` DESC LIMIT " . $pPage . " OFFSET " . ($page - 1) * $pPage);
        $count = DatabaseConnection::i()->getConnection()->query("SELECT COUNT(*) " . $queryBase)->fetch()->{"COUNT(*)"};
        
        $this->template->_template     = "Wall/Feed.xml";
        $this->template->globalFeed    = true;
        $this->template->paginatorConf = (object) [
            "count"   => $count,
            "page"    => (int) ($_GET["p"] ?? 1),
            "amount"  => $posts->getRowCount(),
            "perPage" => $pPage,
        ];
        foreach($posts as $post)
            $this->template->posts[] = $this->posts->get($post->id);
    }
    
    function renderHashtagFeed(string $hashtag): void
    {
        $hashtag = rawurldecode($hashtag);
        
        $page  = (int) ($_GET["p"] ?? 1);
        $posts = $this->posts->getPostsByHashtag($hashtag, $page);
        $count = $this->posts->getPostCountByHashtag($hashtag);
        
        $this->template->hashtag       = $hashtag;
        $this->template->posts         = $posts;
        $this->template->paginatorConf = (object) [
            "count"   => 0,
            "page"    => $page,
            "amount"  => $count,
            "perPage" => OPENVK_DEFAULT_PER_PAGE,
        ];
    }
    
    function renderMakePost(int $wall): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $wallOwner = ($wall > 0 ? (new Users)->get($wall) : (new Clubs)->get($wall * -1))
                     ?? $this->flashFail("err", tr("failed_to_publish_post"), tr("error_4"));

        if ($wallOwner instanceof User && $wallOwner->isServiceAccount())
            $this->flashFail("err", tr("error"), tr("forbidden"));

        if($wall > 0) {
            if(!$wallOwner->isBanned() && !$wallOwner->isDeleted() && $wallOwner->canBeViewedBy($this->user->identity))
                $canPost = $wallOwner->getPrivacyPermission("wall.write", $this->user->identity);
            else
                $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));
        } else if($wall < 0) {
            if(!$wallOwner->isDeleted()) {
                if($wallOwner->canBeModifiedBy($this->user->identity))
                    $canPost = true;
                else
                    $canPost = $wallOwner->canPost();
            } else {
                $canPost = false;
            }

        } else {
            $canPost = false; 
        }
	
        if(!$canPost)
            $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));

        $anon = OPENVK_ROOT_CONF["openvk"]["preferences"]["wall"]["anonymousPosting"]["enable"];
        if($wallOwner instanceof Club && $this->postParam("as_group") === "on" && $this->postParam("force_sign") !== "on" && $anon) {
            $manager = $wallOwner->getManager($this->user->identity);
            if($manager)
                $anon = $manager->isHidden();
            elseif($this->user->identity->getId() === $wallOwner->getOwner()->getId())
                $anon = $wallOwner->isOwnerHidden();
        } else {
            $anon = $anon && $this->postParam("anon") === "on";
        }
        
        $flags = 0;
        if($this->postParam("as_group") === "on" && $wallOwner instanceof Club && $wallOwner->canBeModifiedBy($this->user->identity))
            $flags |= 0b10000000;
        if($this->postParam("force_sign") === "on")
            $flags |= 0b01000000;
        
            $photos = [];

            if(!empty($this->postParam("photos"))) {
                $un  = rtrim($this->postParam("photos"), ",");
                $arr = explode(",", $un);

                if(sizeof($arr) < 11) {
                    foreach($arr as $dat) {
                        $ids = explode("_", $dat);
                        $photo = (new Photos)->getByOwnerAndVID((int)$ids[0], (int)$ids[1]);
    
                        if(!$photo || $photo->isDeleted())
                            continue;
    
                        $photos[] = $photo;
                    }
            }
        }
        
        try {
            $poll = NULL;
            $xml = $this->postParam("poll");
            if (!is_null($xml) && $xml != "none")
                $poll = Poll::import($this->user->identity, $xml);
        } catch(TooMuchOptionsException $e) {
            $this->flashFail("err", tr("failed_to_publish_post"), tr("poll_err_to_much_options"));
        } catch(\UnexpectedValueException $e) {
            $this->flashFail("err", tr("failed_to_publish_post"), "Poll format invalid");
        }

        $note = NULL;

        if(!is_null($this->postParam("note")) && $this->postParam("note") != "none") {
            $note = (new Notes)->get((int)$this->postParam("note"));

            if(!$note || $note->isDeleted() || $note->getOwner()->getId() != $this->user->id) {
                $this->flashFail("err", tr("error"), tr("error_attaching_note"));
            }

            if($note->getOwner()->getPrivacySetting("notes.read") < 1) {
                $this->flashFail("err", " ");
            }
        }

        $geo = NULL;

        if (!is_null($this->postParam("geo")) && $this->postParam("geo") != "none") {
            $geo = json_decode($this->postParam("geo"), true, JSON_UNESCAPED_UNICODE);
            if (!$geo["lat"] || !$geo["lng"] || !$geo["name"]) {
                $this->flashFail("err", tr("error"), tr("error_geolocation"));
            }

            $latitude = number_format((float) $geo["lat"], 8, ".", '');
            $longitude = number_format((float) $geo["lng"], 8, ".", '');
            if ($latitude > 90 || $latitude < -90 || $longitude > 180 || $longitude < -180) {
                $this->flashFail("err", tr("error"), "Invalid latitude or longitude");
            }
        }

        $videos = [];

        if(!empty($this->postParam("videos"))) {
            $un  = rtrim($this->postParam("videos"), ",");
            $arr = explode(",", $un);

            if(sizeof($arr) < 11) {
                foreach($arr as $dat) {
                    $ids = explode("_", $dat);
                    $video = (new Videos)->getByOwnerAndVID((int)$ids[0], (int)$ids[1]);

                    if(!$video || $video->isDeleted())
                        continue;

                    $videos[] = $video;
                }
            }
        }

        $audios = [];

        if(!empty($this->postParam("audios"))) {
            $un  = rtrim($this->postParam("audios"), ",");
            $arr = explode(",", $un);

            if(sizeof($arr) < 11) {
                foreach($arr as $dat) {
                    $ids = explode("_", $dat);
                    $audio = (new Audios)->getByOwnerAndVID((int)$ids[0], (int)$ids[1]);

                    if(!$audio || $audio->isDeleted())
                        continue;

                    $audios[] = $audio;
                }
            }
        }
        
        if(empty($this->postParam("text")) && sizeof($photos) < 1 && sizeof($videos) < 1 && sizeof($audios) < 1 && !$poll && !$note && !$geo)
            $this->flashFail("err", tr("failed_to_publish_post"), tr("post_is_empty_or_too_big"));
        
        try {
            $post = new Post;
            $post->setOwner($this->user->id);
            $post->setWall($wall);
            $post->setCreated(time());
            $post->setContent($this->postParam("text"));
            $post->setAnonymous($anon);
            $post->setFlags($flags);
            $post->setNsfw($this->postParam("nsfw") === "on");
            if ($geo) {
                $post->setGeo(json_encode($geo));
                $post->setGeo_Lat($latitude);
                $post->setGeo_Lon($longitude);
            }

            if($this->postParam("set_source") === "on" && !is_null($this->postParam("source")) && !empty($this->postParam("source")) && preg_match("/^(http:\/\/|https:\/\/)*[а-яА-ЯёЁa-z0-9\-_]+(\.[а-яА-ЯёЁa-z0-9\-_]+)+(\/\S*)*$/iu", $this->postParam("source")) && iconv_strlen($this->postParam("set_source")) < 50) {
                $post->setSource($this->postParam("source"));
            }

            if($wall < 0 && !$wallOwner->canBeModifiedBy($this->user->identity) && $wallOwner->getWallType() == 2)
                $post->setSuggested(1);

            $post->save();
        } catch (\LengthException $ex) {
            $this->flashFail("err", tr("failed_to_publish_post"), tr("post_is_too_big"));
        }
        
        foreach($photos as $photo)
        	    $post->attach($photo);
        
        if(sizeof($videos) > 0)
            foreach($videos as $vid)
                $post->attach($vid);
        
        if(!is_null($poll))
            $post->attach($poll);

        if(!is_null($note))
            $post->attach($note);
        
        foreach($audios as $audio)
        	$post->attach($audio);    
        
        if($wall > 0 && $wall !== $this->user->identity->getId())
            (new WallPostNotification($wallOwner, $post, $this->user->identity))->emit();
        
        $excludeMentions = [$this->user->identity->getId()];
        if($wall > 0)
            $excludeMentions[] = $wall;

        if($wall < 0 && !$wallOwner->canBeModifiedBy($this->user->identity) && $wallOwner->getWallType() == 2) {
            # ребята, приключений час
        } else {
            $mentions = iterator_to_array($post->resolveMentions($excludeMentions));

            foreach($mentions as $mentionee)
                if($mentionee instanceof User)
                    (new MentionNotification($mentionee, $post, $post->getOwner(), strip_tags($post->getText())))->emit();
        }
        
        $this->redirect($wallOwner->getURL());

        if($wall < 0 && !$wallOwner->canBeModifiedBy($this->user->identity) && $wallOwner->getWallType() == 2) {
            $suggsCount = $this->posts->getSuggestedPostsCount($wallOwner->getId());

            # Возможно, это заебёт админов групп, но так они хотя бы про паблик вспомнят
            # Мб рандома добавить?
            if($suggsCount % 10 == 0) {
                $managers = $wallOwner->getManagers();
                $owner = $wallOwner->getOwner();
                (new NewSuggestedPostsNotification($owner, $wallOwner))->emit();

                foreach($managers as $manager) 
                    (new NewSuggestedPostsNotification($manager->getUser(), $wallOwner))->emit();
            }

            $this->redirect("/club".$wallOwner->getId()."/suggested");
        } else {
            $this->redirect($wallOwner->getURL());
        }
    }
    
    function renderPost(int $wall, int $post_id): void
    {
        $post = $this->posts->getPostById($wall, $post_id);
        if(!$post || $post->isDeleted() || $post->getWallOwner()->isDeleted())
            $this->notFound();

        if ($post->getOwner() instanceof User && $post->getOwner()->isServiceAccount())
            $this->flashFail("err", tr("error"), tr("forbidden"));    

        if(!$post->canBeViewedBy($this->user->identity))
            $this->flashFail("err", tr("error"), tr("forbidden"));    
        
        $this->logPostView($post, $wall);
        
        $this->template->post     = $post;
        if ($post->getTargetWall() > 0) {
        	$this->template->wallOwner = (new Users)->get($post->getTargetWall());
			$this->template->isWallOfGroup = false;
            if($this->template->wallOwner->isBanned() || $this->template->wallOwner->isDeleted())
                $this->flashFail("err", tr("error"), tr("forbidden"));
		} else {
			$this->template->wallOwner = (new Clubs)->get(abs($post->getTargetWall()));
			$this->template->isWallOfGroup = true;
		}
        $this->template->cCount   = $post->getCommentsCount();
        $this->template->cPage    = (int) ($_GET["p"] ?? 1);
        $this->template->comments = iterator_to_array($post->getComments($this->template->cPage));
    }
    
    function renderLike(int $wall, int $post_id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        $this->assertNoCSRF();
        
        $post = $this->posts->getPostById($wall, $post_id);
        if(!$post || $post->isDeleted() || $post->getWallOwner()->isDeleted() || ($post->getOwner() instanceof User && $post->getOwner()->isServiceAccount())) $this->notFound();

        if(!$post->canBeViewedBy($this->user->identity)) {
            $this->flashFail("err", tr("error"), tr("forbidden"));
        }
        
        if(!is_null($this->user)) {
            $post->toggleLike($this->user->identity);
        }
        
        $this->redirect("$_SERVER[HTTP_REFERER]#postGarter=" . $post->getId());
    }
    
    function renderShare(int $wall, int $post_id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        $this->assertNoCSRF();
        
        $post = $this->posts->getPostById($wall, $post_id);

        if(!$post || $post->isDeleted() ||  $post->getWallOwner()->isDeleted() || ($post->getOwner() instanceof User && $post->getOwner()->isServiceAccount())) 
            $this->notFound();
        
        if($post->getWallOwner() instanceof User) {
            if(!$post->getWallOwner()->canBeViewedBy($this->user->identity))
                $this->returnJson([
                    "error" => "forbidden"
                ]);
        }

        $where = $this->postParam("type") ?? "wall";
        $groupId = NULL;
        $flags = 0;

        if($where == "group")
            $groupId = $this->postParam("groupId");

        if(!is_null($this->user)) {
            $nPost = new Post;

            if($where == "wall") {
                $nPost->setOwner($this->user->id);
                $nPost->setWall($this->user->id);
            } elseif($where == "group") {
                $nPost->setOwner($this->user->id);
                $club = (new Clubs)->get((int)$groupId);

                if(!$club || !$club->canBeModifiedBy($this->user->identity))
                    $this->notFound();
                
                if($this->postParam("asGroup") == 1) 
                    $flags |= 0b10000000;

                if($this->postParam("signed") == 1)
                    $flags |= 0b01000000;
                
                $nPost->setWall($groupId * -1);
            }

            $nPost->setContent($this->postParam("text"));
            $nPost->setFlags($flags);
            $nPost->save();

            $nPost->attach($post);
            
            if($post->getOwner(false)->getId() !== $this->user->identity->getId() && !($post->getOwner() instanceof Club))
                (new RepostNotification($post->getOwner(false), $post, $this->user->identity))->emit();
        };
        
        $this->returnJson([
            "wall_owner" => $where == "wall" ? $this->user->identity->getId() : $groupId * -1
        ]);
    }
    
    function renderDelete(int $wall, int $post_id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $post = $this->posts->getPostById($wall, $post_id, true);
        if(!$post)
            $this->notFound();
        $user = $this->user->id;

        $wallOwner = ($wall > 0 ? (new Users)->get($wall) : (new Clubs)->get($wall * -1))
                     ?? $this->flashFail("err", tr("failed_to_delete_post"), tr("error_4"));

        if($wall < 0) $canBeDeletedByOtherUser = $wallOwner->canBeModifiedBy($this->user->identity);
            else $canBeDeletedByOtherUser = false;

        if(!is_null($user)) {
            if($post->getTargetWall() < 0 && !$post->getWallOwner()->canBeModifiedBy($this->user->identity) && $post->getWallOwner()->getWallType() != 1 && $post->getSuggestionType() == 0)
                $this->flashFail("err", tr("failed_to_delete_post"), tr("error_deleting_suggested"));

            if($post->getOwnerPost() == $user || $post->getTargetWall() == $user || $canBeDeletedByOtherUser) {
                $post->unwire();
                $post->delete();
            }
        } else {
            $this->flashFail("err", tr("failed_to_delete_post"), tr("login_required_error_comment"));
        }
        
        $this->redirect($wall < 0 ? "/club" . ($wall*-1) : "/id" . $wall);
    }
    
    function renderPin(int $wall, int $post_id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $post = $this->posts->getPostById($wall, $post_id);
        if(!$post)
            $this->notFound();
        
        if(!$post->canBePinnedBy($this->user->identity))
            $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));
        
        if(($this->queryParam("act") ?? "pin") === "pin") {
            $post->pin();
        } else {
            $post->unpin();
        }
        
        # TODO localize message based on language and ?act=(un)pin
        $this->flashFail("succ", tr("information_-1"), tr("changes_saved_comment"));
    }

    function renderEdit()
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();

        if($_SERVER["REQUEST_METHOD"] !== "POST")
            $this->redirect("/id0");

        if($this->postParam("type") == "post")
            $post = $this->posts->get((int)$this->postParam("postid"));
        else
            $post = (new Comments)->get((int)$this->postParam("postid"));

        if(!$post || $post->isDeleted())
            $this->returnJson(["error" => "Invalid post"]);

        if(!$post->canBeEditedBy($this->user->identity))
            $this->returnJson(["error" => "Access denied"]);

        $attachmentsCount = sizeof(iterator_to_array($post->getChildren()));

        if(empty($this->postParam("newContent")) && $attachmentsCount < 1)
            $this->returnJson(["error" => "Empty post"]);

        $post->setEdited(time());

        try {
            $post->setContent($this->postParam("newContent"));
        } catch(\LengthException $e) {
            $this->returnJson(["error" => $e->getMessage()]);
        }

        if($this->postParam("type") === "post") {
            $post->setNsfw($this->postParam("nsfw") == "true");
            $flags = 0;

            if($post->getTargetWall() < 0 && $post->getWallOwner()->canBeModifiedBy($this->user->identity)) {
                if($this->postParam("fromgroup") == "true") {
                    $flags |= 0b10000000;
                    $post->setFlags($flags);
                } else
                    $post->setFlags($flags);
            }
        }

        $post->save(true);

        $this->returnJson(["error"    => "no", 
                        "new_content" => $post->getText(), 
                        "new_edited"  => (string)$post->getEditTime(),
                        "nsfw"        => $this->postParam("type") === "post" ? (int)$post->isExplicit() : 0,
                        "from_group"  => $this->postParam("type") === "post" && $post->getTargetWall() < 0 ?
                        ((int)$post->isPostedOnBehalfOfGroup()) : "false",
                        "new_text"    => $post->getText(false),
                        "author"      => [
                            "name"    => $post->getOwner()->getCanonicalName(),
                            "avatar"  => $post->getOwner()->getAvatarUrl()
                        ]]);
    }

    function renderAccept() {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction(true);

        if($_SERVER["REQUEST_METHOD"] !== "POST") {
            header("HTTP/1.1 405 Method Not Allowed");
            exit("Ты дебил, это точка апи.");
        }

        $id = $this->postParam("id");
        $sign = $this->postParam("sign") == 1;
        $content = $this->postParam("new_content");

        $post = (new Posts)->get((int)$id);

        if(!$post || $post->isDeleted())
            $this->flashFail("err", "Error", tr("error_accepting_invalid_post"), NULL, true);

        if($post->getSuggestionType() == 0)
            $this->flashFail("err", "Error", tr("error_accepting_not_suggested_post"), NULL, true);

        if($post->getSuggestionType() == 2)
            $this->flashFail("err", "Error", tr("error_accepting_declined_post"), NULL, true);

        if(!$post->canBePinnedBy($this->user->identity))
            $this->flashFail("err", "Error", "Can't accept this post.", NULL, true);

        $author = $post->getOwner();

        $flags = 0;
        $flags |= 0b10000000;

        if($sign)
            $flags |= 0b01000000;

        $post->setSuggested(0);
        $post->setCreated(time());
        $post->setApi_Source_Name(NULL);
        $post->setFlags($flags);

        if(mb_strlen($content) > 0)
            $post->setContent($content);

        $post->save();

        if($author->getId() != $this->user->id)
            (new PostAcceptedNotification($author, $post, $post->getWallOwner()))->emit();

        $this->returnJson([
            "success"   => true,
            "id"        => $post->getPrettyId(),
            "new_count" => (new Posts)->getSuggestedPostsCount($post->getWallOwner()->getId())
        ]);
    }

    function renderDecline() {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction(true);

        if($_SERVER["REQUEST_METHOD"] !== "POST") {
            header("HTTP/1.1 405 Method Not Allowed");
            exit("Ты дебил, это метод апи.");
        }

        $id = $this->postParam("id");
        $post = (new Posts)->get((int)$id);

        if(!$post || $post->isDeleted())
            $this->flashFail("err", "Error", tr("error_declining_invalid_post"), NULL, true);

        if($post->getSuggestionType() == 0)
            $this->flashFail("err", "Error", tr("error_declining_not_suggested_post"), NULL, true);

        if($post->getSuggestionType() == 2)
            $this->flashFail("err", "Error", tr("error_declining_declined_post"), NULL, true);

        if(!$post->canBePinnedBy($this->user->identity))
            $this->flashFail("err", "Error", "Can't decline this post.", NULL, true);

        $post->setSuggested(2);
        $post->setDeleted(1);
        $post->save();

        $this->returnJson([
            "success"   => true,
            "new_count" => (new Posts)->getSuggestedPostsCount($post->getWallOwner()->getId())
        ]);
    }

    function renderNearest(int $wall, int $post_id): void
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") $this->notFound();
        $this->assertUserLoggedIn();

        $post = $this->posts->getPostById($wall, $post_id);
        if(!$post)
            $this->notFound();

        $lat = $post->getLat();
        $lon = $post->getLon();

        if (!$lat || !$lon)
            $this->returnJson(["success" => false, "error" => tr("error_no_geotag")]);

        $query = file_get_contents(__DIR__ . "/../Models/sql/get-nearest-posts.tsql");
        $_posts = DatabaseConnection::i()->getContext()->query($query, $lat, $lon, $post->getId())->fetchAll();
        $posts = [];
        foreach ($_posts as $post) {
            $distance = $post["distance"];
            $post = (new Posts)->get($post["id"]);
            if (!$post || $post->isDeleted()) continue;

            $owner = $post->getOwner();

            $preview = mb_substr($post->getText(), 0, 50) . (strlen($post->getText()) > 50 ? "..." : "");
            $posts[] = [
                "preview" => strlen($preview) > 0 ? $preview : "(нет текста)",
                "url" => "/wall" . $post->getPrettyId(),
                "time" => $post->getPublicationTime()->html(),
                "owner" => [
                    "url" => $owner->getURL(),
                    "avatar_url" => $owner->getAvatarURL(),
                    "name" => $owner->getCanonicalName(),
                    "verified" => $owner->isVerified(),
                    "writes" => ($owner instanceof User) ? ($owner->isFemale() ? tr("post_writes_f") : tr("post_writes_m")) : tr("post_writes_m")
                ],
                "geo" => $post->getGeo(),
                "distance" => $distance
            ];
        }

        $this->returnJson(["success" => true, "posts" => $posts, "need_count" => count($posts) === 25]);
    }

    function renderIgnoreSource()
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction(true);

        if($_SERVER["REQUEST_METHOD"] !== "POST")
            exit("куда ты полез?");

        $owner         = $this->user->id;
        $ignoredSource = (int)$this->postParam("source");

        if($this->user->identity->getIgnoredSourcesCount() > 50)
            $this->flashFail("err", "Error", tr("max_ignores", 50), null, true);

        if($ignoredSource > 0) {
            $ignoredSourceModel = (new Users)->get($ignoredSource);

            if(!$ignoredSourceModel)
                $this->flashFail("err", "Error", tr("invalid_user"), null, true);

            if($ignoredSourceModel->getId() == $this->user->id)
                $this->flashFail("err", "Error", tr("cant_ignore_self"), null, true);
            
            if($ignoredSourceModel->isClosed())
                $this->flashFail("err", "Error", tr("no_sense"), null, true);
        } else {
            $ignoredSourceModel = (new Clubs)->get(abs($ignoredSource));

            if(!$ignoredSourceModel)
                $this->flashFail("err", "Error", tr("invalid_club"), null, true);

            if($ignoredSourceModel->isHideFromGlobalFeedEnabled()) 
                $this->flashFail("err", "Error", tr("no_sense"), null, true);
        }

        if(!$ignoredSourceModel->toggleIgnore($this->user->identity)) {
            $tr = "";

            if($ignoredSource > 0)
                $tr = tr("ignore_user");
            else
                $tr = tr("ignore_club");

            $this->returnJson(["success" => true, "act" => "unignored", "text" => $tr]);
        } else {
            if($ignoredSource > 0)
                $tr = tr("unignore_user");
            else
                $tr = tr("unignore_club");

            $this->returnJson(["success" => true, "act" => "ignored", "text" => $tr]);
        }
    }
}
