<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities\Traits;
use openvk\Web\Models\Repositories\{Users, Clubs};
use Wkhooy\ObsceneCensorRus;

trait TRichText
{
    private function formatKolobki(string $text): string
{
    $contentColumn = $this->overrideContentColumn ?? "content";
    if (iconv_strlen($this->getRecord()->{$contentColumn}) > OPENVK_ROOT_CONF["openvk"]["preferences"]["wall"]["postSizes"]["emojiProcessingLimit"]) {
        return $text;
    }

    $kolobki = [
        'acute' => 'acute', 'aggressive' => 'aggressive', 'agree' => 'agree', 'aikido' => 'aikido', 'air_kiss' => 'air_kiss',
        'alcoholic' => 'alcoholic', 'angel' => 'angel', 'bad' => 'bad', 'banned' => 'banned',
        'beach' => 'beach', 'beee' => 'beee', 'fu' => 'bad', 'cool' => 'bd', 'big_boss' => 'big_boss', 'black_eye' => 'black_eye',
        'blind' => 'blind', 'blum2' => 'blum2', 'blum3' => 'blum3', 'blush2' => 'blush2', 'boast' => 'boast',
        'boredom' => 'boredom', 'brunette' => 'brunette', 'buba' => 'buba', 'buba_phone' => 'buba_phone', 'butcher' => 'butcher',
        'censored' => 'censored', 'clapping' => 'clapping', 'comando' => 'comando', 'cray' => 'cray', 'cray2' => 'cray2',
        'kek' => 'crazy', 'crazy_pilot' => 'crazy_pilot', 'curtsey' => 'curtsey', 'dance' => 'dance', 'dance2' => 'dance2', 'dirol' => 'dirol', 'dash1' => 'dash1',
        'declare' => 'declare', 'dinamo' => 'dinamo', 'don-t_mention' => 'don-t_mention', 'download1' => 'download',
        'pivo' => 'drinks', 'dwarf' => 'dwarf', 'elf' => 'elf', 'facepalm' => 'facepalm', 
        'first_move' => 'first_move', 'flirt' => 'flirt', 'focus' => 'focus', 'fool' => 'fool', 'friends' => 'friends',
        'gamer1' => 'gamer1', 'gamer2' => 'gamer2', 'gamer3' => 'gamer3', 'gamer4' => 'gamer4', 'give_heart' => 'give_heart',
        'give_rose' => 'give_rose', 'good' => 'good', 'good2' => 'good2', 'good3' => 'good3', 'heat' => 'heat', 'help' => 'help',
        'hi' => 'hi', 'hunter' => 'hunter', 'hysteric' => 'hysteric', 'i-m_so_happy' => 'i-m_so_happy', 'ireful1' => 'ireful1',
        'ireful2' => 'ireful2', 'ireful3' => 'ireful3', 'jester' => 'jester', 'king' => 'king', 'king2' => 'king2',
        'kiss' => 'kiss', 'kiss2' => 'kiss2', 'kiss3' => 'kiss3', 'laugh1' => 'laugh1', 'laugh2' => 'laugh2', 'laugh3' => 'laugh3',
        'lazy' => 'lazy', 'lazy2' => 'lazy2', 'lazy3' => 'lazy3', 'locomotive' => 'locomotive', 'mail1' => 'mail1', 'mamba' => 'mamba',
        'man_in_love' => 'man_in_love', 'love' => 'love', 'mda' => 'mda', 'music' => 'music', 'ok' => 'ok', 'orc' => 'orc',
        'paint' => 'paint', 'patsak' => 'patsak', 'phi' => 'phi', 'pilot' => 'pilot', 'pioneer' => 'pioneer', 'pleasantry' => 'pleasantry',
        'popcorm1' => 'popcorm1', 'preved' => 'preved', 'protest' => 'protest', 'queen' => 'queen', 'rabbi' => 'rabbi', 'read' => 'read',
        'resent' => 'resent', 'lool' => 'rofl', 'sad' => 'sad', 'sarcasm' => 'sarcasm', 'sarcastic' => 'sarcastic', 'scare' => 'scare',
        'sclerosis' => 'sclerosis', 'scout' => 'scout', 'scout_en' => 'scout_en', 'scratch_one-s_head' => 'scratch_one-s_head',
        'search' => 'search', 'secret' => 'secret', 'shablon_01' => 'shablon_01', 'shablon_02' => 'shablon_02', 'shablon_03' => 'shablon_03',
        'shablon_04' => 'shablon_04', 'shout' => 'shout', 'slow' => 'slow', 'slow_en' => 'slow_en', 'smile' => 'smile', 'smoke' => 'smoke',
        'snooks' => 'snooks', 'sorry' => 'sorry', 'sorry2' => 'sorry2', 'zlo' => 'zlo', 'spruce_up' => 'spruce_up', 'stinker' => 'stinker',
        'stop' => 'stop', 'sun_bespectacled' => 'sun_bespectacled', 'superman' => 'superman', 'superman2' => 'superman2',
        'superstition' => 'superstition', 'swoon' => 'swoon', 'swoon2' => 'swoon2', 'take_example' => 'take_example',
        'taunt' => 'taunt', 'tease' => 'tease', 'telephone' => 'telephone', 'tender' => 'tender', 'thank_you' => 'thank_you',
        'thank_you2' => 'thank_you2', 'this' => 'this', 'tommy' => 'tommy', 'to_babruysk' => 'to_babruysk', 'to_become_senile' => 'to_become_senile',
        'to_clue' => 'to_clue', 'to_keep_order' => 'to_keep_order', 'to_pick_ones_nose' => 'to_pick_ones_nose', 'to_pick_ones_nose2' => 'to_pick_ones_nose2',
        'to_pick_ones_nose3' => 'to_pick_ones_nose3', 'to_pick_ones_nose_eat' => 'to_pick_ones_nose_eat', 'to_take_umbrage' => 'to_take_umbrage',
        'training1' => 'training1', 'triniti' => 'triniti', 'umnik' => 'umnik', 'umnik2' => 'umnik2', 'vampire' => 'vampire', 'victory' => 'victory',
        'vinsent' => 'vinsent', 'wacko' => 'wacko', 'wacko2' => 'wacko2', 'warning' => 'warning', 'warning2' => 'warning2', 'whistle' => 'whistle',
        'whistle2' => 'whistle2', 'whistle3' => 'whistle3', 'wild' => 'wild', 'wink3' => 'wink3', 'wizard' => 'wizard', 'yahoo' => 'yahoo',
        'yes2' => 'yes2', 'yes3' => 'yes3', 'yes4' => 'yes4', 'yu' => 'yu'
    ];

    foreach ($kolobki as $emoji => $file) {
        $text = preg_replace(
            '/\b' . preg_quote($emoji, '/') . '\b/',
            "<img src='/assets/packages/static/openvk/kolobki/{$file}.gif' style='max-height:30px; padding-left:2pt; padding-right:2pt; vertical-align: middle;' />",
            $text
        );
    }

    return $text;
}
 
    private function formatLinks(string &$text): string
    {
        return preg_replace_callback(
            "%(([A-z]++):\/\/(\S*?\.\S*?))([\s)\[\]{},\"\'<]|\.\s|$)%",
            (function (array $matches): string {
                $href = str_replace("#", "&num;", $matches[1]);
                $href = rawurlencode(str_replace(";", "&#59;", $href));
                $link = str_replace("#", "&num;", $matches[3]);
                $link = str_replace(";", "&#59;", $matches[3]);
                $rel  = $this->isAd() ? "sponsored" : "ugc";
                
                return "<a href='/away.php?to=$href' rel='$rel' target='_blank'>$link</a>" . htmlentities($matches[4]);
            }),
            $text
        );
    }
    
    private function removeZalgo(string $text): string
    {
        return preg_replace("%\p{M}{3,}%Xu", "", $text);
    }
	
	function resolveMentions(array $skipUsers = []): \Traversable
    {
        $contentColumn = property_exists($this, "overrideContentColumn") ? $this->overrideContentColumn : "content";
        $text = $this->getRecord()->{$contentColumn};
        $text = preg_replace("%@([A-Za-z0-9]++) \(((?:[\p{L&}\p{Lo} 0-9]\p{Mn}?)++)\)%Xu", "[$1|$2]", $text);
        $text = preg_replace("%([\n\r\s]|^)(@([A-Za-z0-9]++))%Xu", "$1[$3|@$3]", $text);

        $resolvedUsers = $skipUsers;
        $resolvedClubs = [];
        preg_match_all("%\[([A-Za-z0-9]++)\|((?:[\p{L&}\p{Lo} 0-9@]\p{Mn}?)++)\]%Xu", $text, $links, PREG_PATTERN_ORDER);
        foreach($links[1] as $link) {
            if(preg_match("%^id([0-9]++)$%", $link, $match)) {
                $uid = (int) $match[1];
                if(in_array($uid, $resolvedUsers))
                    continue;

                $resolvedUsers[] = $uid;
                $maybeUser = (new Users)->get($uid);
                if($maybeUser)
                    yield $maybeUser;
            } else if(preg_match("%^(?:club|public|event)([0-9]++)$%", $link, $match)) {
                $cid = (int) $match[1];
                if(in_array($cid, $resolvedClubs))
                    continue;

                $resolvedClubs[] = $cid;
                $maybeClub = (new Clubs)->get($cid);
                if($maybeClub)
                    yield $maybeClub;
            } else {
                $maybeUser = (new Users)->getByShortURL($link);
                if($maybeUser) {
                    $uid = $maybeUser->getId();
                    if(in_array($uid, $resolvedUsers))
                        continue;
                    else
                        $resolvedUsers[] = $uid;

                    yield $maybeUser;
                    continue;
                }

                $maybeClub = (new Clubs)->getByShortURL($link);
                if($maybeClub) {
                    $cid = $maybeClub->getId();
                    if(in_array($cid, $resolvedClubs))
                        continue;
                    else
                        $resolvedClubs[] = $cid;

                    yield $maybeClub;
                }
            }
        }
    }
    
    function getText(bool $html = true): string
    {
        $contentColumn = property_exists($this, "overrideContentColumn") ? $this->overrideContentColumn : "content";
        
        $text = htmlspecialchars($this->getRecord()->{$contentColumn}, ENT_DISALLOWED | ENT_XHTML);
        $proc = iconv_strlen($this->getRecord()->{$contentColumn}) <= OPENVK_ROOT_CONF["openvk"]["preferences"]["wall"]["postSizes"]["processingLimit"];
        if($html) {
            if($proc) {
                $text = $this->formatLinks($text);
                $text = preg_replace("%@([A-Za-z0-9]++) \(((?:[\p{L&}\p{Lo} 0-9]\p{Mn}?)++)\)%Xu", "[$1|$2]", $text);
                $text = preg_replace("%([\n\r\s]|^)(@([A-Za-z0-9]++))%Xu", "$1[$3|@$3]", $text);
                $text = preg_replace("%\[([A-Za-z0-9]++)\|((?:[\p{L&}\p{Lo} 0-9@]\p{Mn}?)++)\]%Xu", "<a href='/$1'>$2</a>", $text);
                $text = preg_replace_callback("%([\n\r\s]|^)(\#([\p{L}_0-9][\p{L}_0-9\(\)\-\']+[\p{L}_0-9\(\)]|[\p{L}_0-9]{1,2}))%Xu", function($m) {
                    $slug = rawurlencode($m[2]);
                    
                    return "$m[1]<a href='/feed/hashtag/$slug'>$m[3]</a>";
                }, $text);
                
                $text = $this->formatKolobki($text);
            }
            
            $text = $this->removeZalgo($text);
            $text = nl2br($text);
        }
        
        if(OPENVK_ROOT_CONF["openvk"]["preferences"]["wall"]["christian"])
            ObsceneCensorRus::filterText($text);
        
        return $text;
    }
}
