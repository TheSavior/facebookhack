<?php
class History {
    public $user;
    private $service;

    public function __construct($service, $user){
        $this->user = $user;
        $this->service = $service;
    }

    public function run() {

        $prof = json_decode($this->service->request('/'. $this->user));
        $userId = $prof->id;

        $val = $this->service->request('/'.$this->user.'/feed?limit=500');
        $json = json_decode($val);

        $mutualFriends = json_decode($this->service->request("me/mutualfriends/".$userId));
        //die(var_dump($mutualFriends->data));
        $mut = array();
        foreach ($mutualFriends->data as $friend) {
            $mut[] = $friend->name;
        }

        $newFriends = array();
        $stories = 0;

        $types = array();
        $storyArray = array();

        foreach($json->data as $ele) {
            //die(var_dump($ele->status_type == "approved_friend"));
            if (property_exists($ele, "status_type") && $ele->status_type == "approved_friend") {
                foreach($ele->story_tags as $tag) {
                    if ($tag[0]->id != $userId) {
                       $newFriends[] = $tag[0]->name; 
                    }
                    
                }
            }
            else
            {
                $likeCount = 0;
                if (property_exists($ele, "likes")) {
                    $likeCount = $ele->likes->count;
                }

                $storyArray[] = array("likes" => $likeCount, "original" => $ele);


                if (!isset($types[$ele->type]))
                {
                    $types[$ele->type] = 1;
                }
                else
                {
                    $types[$ele->type]++;
                }

                //var_dump($ele);
            }
            $stories++;
        }

        usort($storyArray, function ($a, $b) {
            return $a["likes"] < $b["likes"];
        });


        $newStories = array(
            "photos" => $this->getImportant("photo", $storyArray, 4),
            "status" => $this->getImportant("status", $storyArray),
            "link" => $this->getImportant("link", $storyArray)
        );

        echo "new mutual friends";
        echo "<ul>";
        $newMutFriends = array_intersect($newFriends, $mut);
        foreach($newMutFriends as $newFriend) {
            echo "<li>".$newFriend."</li>";
        }
        echo "</ul>";
        echo "<br />";
        
        
        foreach($newStories as $type => $list) {
            echo "Type: ".$type."<br />";
            echo "<ul>";
            foreach($list as $story) {
                if (property_exists($story["original"], "picture")) {
                    echo '<img src="'.$story["original"]->picture.'" />';
                }
                echo "<li>".$story["likes"]." likes, type: ".$story["original"]->type."</li>";
            }
            echo "</ul>";
        }
        
        echo "<br />\n";
        var_dump($types);
    }

    function getImportant($type, $stories, $limit = 0) {
        $popStories = array_filter($stories, function($item) use ($type, $limit){
            //die(var_dump($item["original"]->type));
            return $item["likes"] > 0 && $item["original"]->type == $type;
        });

        if ($limit > 0) {
            return array_slice($popStories, 0, $limit);
        }

        //die(var_dump(count($popStories)));

        $avg = array_reduce($popStories, function($acc, $item) {
            return $acc + $item["likes"];
        }) / count($popStories);


        $distFromAvg = array_map(function($item) use ($avg) {
            $dist = round(pow($item["likes"] - $avg,2));
            return $dist;
        }, $popStories);

        $stdDev = sqrt(array_sum($distFromAvg) / count($popStories));

        $results = array_filter($popStories, function($item) use ($avg, $stdDev){
            return $item["likes"] > $avg+$stdDev;
        });

        return $results;
    }
}