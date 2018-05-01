<html>
<head>
<title>Labeling Tool</title>

<!--
<link rel="stylesheet" href="/assets/css/bootstrap.min.css" integrity="sha384-WskhaSGFgHYWDcbwN70/dfYBj47jz9qbsMId/iRN3ewGhXQFZCSftd1LZCfmhktB" crossorigin="anonymous">
-->
<style id="atf" data-btf="/assets/css/bootstrap.min.css;/assets/css/style.css">
</style>
<!--
<link rel="stylesheet" type="text/css" href="style.css?v=2">
-->
<!--
<link rel="icon" href="/favicon.ico?v=2">
-->
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>

<div class="modal fade" id="enlargeImageModal" tabindex="-1" role="dialog" aria-labelledby="enlargeImageModal" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
        </div>
        <div class="modal-body">
          <img src="" class="enlargeImageModalSource" style="width: 100%;">
        </div>
      </div>
    </div>
</div>

<div class="container">
<h1>Labeling tool</h1>
<a href="/results/results.txt">Download submitted labels</a>&nbsp;&nbsp;
<a href="/?results">View submitted labels</a><br>
<a href="/">Labeling tool (Skip this image)</a>&nbsp;&nbsp;
<a href="/?reset">Reset memcache data</a><br>
<?php
$root_dir = '/var/www/html';
$image_dir = 'images';
$thumb_dir = 'thumbnails';
$result_file = join('/', array($root_dir, 'results/results.txt'));
$label_form = true;

function printSubmitedLabels($result_file)
{
    global $image_dir, $thumb_dir;

    $contents = file($result_file);
    //var_dump($contents);
    echo "<div class=\"row row-eq-height\">";
    foreach ($contents as $line) {
        $chunks = explode(",", $line);
        if (count($chunks) === 2) {
            $img_path = $chunks[0];
            $thumb_path = $thumb_dir . substr($img_path, strlen($image_dir));
            $classname = trim($chunks[1]);
            $tmp = explode("/", $img_path);
            $target = $tmp[count($tmp) - 2];
            echo "<div class=\"Label col-lg-4 col-xs-12 hover-container ",
                    ($classname === $target ? "CorrectLabel" : "WrongLabel") . "\">",
                "<img class=\"img-thumbnail\" src=\"" . $thumb_path . "\" >",
                "<button type=\"button\" class=\"btn ",
                  ($classname === $target ? "btn-success" : "btn-danger") . "\">",
                  ($classname === $target ? "Correct: " : "Wrong: "),
                  $classname,
                  ($classname === $target ? "" : " (truth = " . $target . ")"),
                "</button></div><br>";
        }
    }
    echo "</div>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST["img_path"]) and isset($_POST["classname"])) {
        $img_path = $_POST["img_path"];
        $classname = $_POST["classname"];
        if (strlen($img_path) < 256 and strlen($classname) < 64) {
            echo "Submitting: " . $classname . "<br>\n";
            $txt = $img_path . "," . $classname;
            $myfile = file_put_contents($result_file, $txt . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
} elseif (isset($_GET['results'])) {
    ?>
  <h3>Labels submmited</h3>
<?php
printSubmitedLabels($result_file);
    $label_form = false;
} // elseif (isset($_GET['results'])) {

if ($label_form) {
    ?>
<div>
  <h3>Label this image and press Submit</h3>
  <form action="/" method="POST">
<?php

    $mc = new Memcached();
    $allclass = array("unknown" => "unknown");
    $data = array();

    class Data
    {
        public $image_dir;
        public $allclass;
        public $data;

        public function __construct($image_dir, $allclass, $data)
        {
            $this->image_dir = $image_dir;
            $this->allclass = $allclass;
            $this->data = $data;
        }

        public function __sleep()
        {
            return array('image_dir', 'allclass', 'data');
        }

        public function __wakeup()
        {
        }
    }

    function setMemcachedOptions()
    {
        global $mc;
        $mc->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
        $mc->setSaslAuthData("www-data", "1234");
        $mc->addServer("127.0.0.1", 11211);
    }

    function getMyCache()
    {
        global $mc, $image_dir, $allclass, $data, $root_dir;
        $response = $mc->get('mycache');
        $mycache = unserialize($response);

        if ($response and isset($mycache->data) and !isset($_GET['reset'])) {
            //echo "Cached mycache<br>";
            $image_dir = $mycache->image_dir;
            $allclass = $mycache->allclass;
            $data = $mycache->data;
        } else {
            $pattern = join("/", array($root_dir, $image_dir, "*/*.jpg"));
            //echo $pattern, "<br>";
            $count = 0;
            foreach (glob($pattern) as $filename) {
                $chunks = explode('/', $filename);
                $len = count($chunks);
                $classname = $chunks[$len - 2];
                $basename = $chunks[$len - 1];
                //echo $classname." ".$basename."<br>";

                $data[$count] = array(
                    "classname" => $classname,
                    "basename" => $basename,
                );
                $allclass[$classname] = $classname;
                $count++;
            }

            $mycache = new Data($image_dir, $allclass, $data);
            $mc->set("mycache", serialize($mycache)) or die("Cannot create new key, mycache not found");
            //echo "Added to cache key=mycache<br>";
            //var_dump($mycache);
        }

    }

    function printRadioAllClass($allclass)
    {
        echo "<div class=\"col-lg-4 col-xs-12\"><div class=\"RadioArea\">";
        foreach ($allclass as $key => $value) {
            echo "<input type=\"radio\" id=\"radio_" . $key . "\" name=\"classname\" value=\"" . $key . "\""
                . ($key === "unknown" ? "checked" : "")
                . "><label for=\"radio_" . $key . "\">&nbsp;" . $value . "</label><br>\n";
        }
        echo "<button type=\"submit\" class=\"btn btn-success\">Submit</button>",
             "<a href=\"/\" class=\"btn btn-link\">Skip this image</a>",
           "</div><br>",
             "<p>Click on image to enlarge</p>",
           "</div>";
    }

    function chooseRandomImage($image_dir, $data)
    {
        $count = count($data);
        if ($count < 1) return;
        $pos = rand(0, $count - 1);
        $img_path = join("/", array($image_dir, $data[$pos]["classname"], $data[$pos]["basename"]));
        echo "<div id=\"ImageContainer\" class=\"col-lg-8 col-xs-12 hover-container \">",
          "<img class=\"img-thumbnail img-thumbnail-large\" src=\"" . $img_path . "\">\n",
        //. "<font color=\"white\">Target: ".$data[$pos]["classname"]."</font><br>\n"
          "</div>",
          "<input type=\"hidden\" name=\"img_path\" value=\"" . $img_path . "\">\n"
        ;
    }

    setMemcachedOptions();
    getMyCache();
?>
    <div class="row">
<?php
    printRadioAllClass($allclass);

    chooseRandomImage($image_dir, $data);
?>
    </div>
  </form>
</div>
<?php
} // if ($label_form) {
?>
</div>
<script src="/assets/js/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
<script src="/assets/js/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
<script src="/assets/js/bootstrap.min.js" integrity="sha384-smHYKdLADwkXOn1EmN1qk/HfnUcbVRZyYmZ4qpPea6sjB/pTJ0euyQp0Mk8ck+5T" crossorigin="anonymous"></script>
<script>
$(function() {
    $('img').on('click', function() {
        thumbpath = $(this).attr('src');
        imgpath = thumbpath.substr(0,'thumbnails'.length) === 'thumbnails' ? 'images' + thumbpath.substr('thumbnails'.length) : thumbpath;
        //console.log(thumbpath, imgpath);
        $('.enlargeImageModalSource').attr('src', imgpath);
        $('#enlargeImageModal').modal('show');
    });
});

(function(){var atf,buff,max,totalLoaded=0;atf=document.getElementById("atf");if(!atf){return false;}buff=atf.getAttribute("data-btf");buff=buff.split(";");max=buff.length;for(var i=0;i<max;i++){if(buff[i]!==""){var link=document.createElement("link");link.rel="stylesheet";link.href=buff[i];link.onload=function(){totalLoaded++;if(totalLoaded>=max){atf.parentElement.removeChild(atf);}};document.head.appendChild(link);}}window.btf=this;})(window);
</script>
<!--
<script async src="/assets/js/btf.js"></script>
-->
</body>
</html>
