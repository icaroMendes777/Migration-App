<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

use App\Models\Post;
use App\Models\FailedFiles;
use App\Models\Collections;

class Migration extends Controller
{



    public function migrate()
    {


        $folderSuttas = 'sutta/';

        //$folder = 'test_sutta/';

        $folderTexts = 'arquivo_textos_theravada/';

        echo 'started...<br/><br/>';

        //$this->migrateSuttasStage1($folderSuttas);

        $this->migrateTextsStage1($folderTexts);

        echo '<h3> Here we are done </h3>';


        return;

    }


    /*


    have a look at:


     ANXI.17,
    */


    public function migrateTextsStage1($dir)
    {
        $files = $this->getClearFileList($dir);

        $count = 0;

        foreach($files as $f){

            $filePath = $dir.$f;

            $count++;

            $textIndex = $this->getSuttaIndexFromFileName($f);

            $fileContent = file_get_contents($dir.'/'.$f);

            $cleanText = $this->correctEncoding( $this->cleanText($fileContent));

            $title = $this->getTitleFromText($cleanText);

            //if(!$translatedTitle) echo 'Not working translated title. File: '.$f.'<br><br>';
            if(!$title){
                $this->errorLog($filePath, 'no title');

                continue;
            }

            $collectionId = $this->getCollectionId('Textos Theravada');

            //echo $filePath.'<br>';
            $data = [
                'collection_id'=>$collectionId,
                'old_url'=>$filePath,
                'index'=>$textIndex,
                'title_pt'=>$title,
                //'title_pali'=>$title,
                'text'=>$cleanText,

            ];

            // echo '<pre>';
            // print_r($data);
            // echo '</pre>';


            //$result = Post::create($data);
            try {
                //code that might cause MySQL errors
                $result = Post::create($data);

            } catch (QueryException $e) {
                // Log the error
                $error = $this->truncateString( $e->getMessage(), 220) ;

                $this->errorLog($filePath, 'no insertion: '.$error);

            }

            if($count == 10) print_r('10 files... <br/>') ;

            if($count == 100) print_r('100 files... <br/>');

            if($count == 170) print_r('170 files... <br/>');

        }


    }

    public function migrateSuttasStage1($dir)
    {
        $files = $this->getClearFileList($dir);

        $count = 0;

        foreach($files as $f){

            $filePath = $dir.$f;

            $count++;

            $suttaIndex = $this->getSuttaIndexFromFileName($f);
            $suttaCollection = $this->getSuttaCollectionFromIndex($suttaIndex);

            $fileContent = file_get_contents($dir.'/'.$f);

            $cleanText = $this->correctEncoding( $this->cleanText($fileContent));

            $title = $this->getTitleFromText($cleanText);

            $translatedTitle = $this->getTranslatedTitle($cleanText);
            //echo $translatedTitle.'<br>';


            //if(!$translatedTitle) echo 'Not working translated title. File: '.$f.'<br><br>';
            if(!$title){
                $this->errorLog($filePath, 'no title');

                continue;
            }

            $collectionId = $this->getCollectionId('Textos Theravada');

            //echo $filePath.'<br>';
            $data = [
                'collection_id'=>$collectionId,
                'old_url'=>$filePath,
                'index'=>$suttaIndex,
                'title_pt'=>$translatedTitle,
                'title_pali'=>$title,
                'text'=>$cleanText,

            ];

            // echo '<pre>';
            // print_r($data);
            // echo '</pre>';


            //$result = Post::create($data);
            try {
                //code that might cause MySQL errors
                $result = Post::create($data);

            } catch (QueryException $e) {
                // Log the error
                $error = $this->truncateString( $e->getMessage(), 220) ;

                $this->errorLog($filePath, 'no insertion: '.$error);

            }

            if($count == 100) print_r('100 files... <br/>') ;

            if($count == 1000) print_r('1000 files... <br/>');

            if($count == 1700) print_r('1700 files... <br/>');

        }


    }

    public function errorLog($filePath, $message ='')
    {
        //echo '???!';
        FailedFiles::create([
            'file_path'=> $filePath,
            'error_message'=> $message
        ]);
    }

    public function getCollectionId($collectionName)
    {
        $collection = Collections::query()
                            ->where('name', $collectionName)
                            ->get()
                            ->first();

        if(!$collection)
                $collection = Collections::create([
                                'name'=>$collectionName,
                            ]);

        return $collection['id'];

    }

    public function getTranslatedTitle($text)
    {


        $text1 = $this->extractLastSubstringBetween($text ,'class=Tit1', 'Tit1>');

        //echo '---'.$text;
        $title = $this->extractLastSubstringBetween($text1 ,'>', '<');


        return $title;
    }

    public function cleanText($text)
    {
        $text = $this->removePHP($text);
        $text = $this->removeNextSuttaLink($text);

        return $text;
    }

    public function removePHP($fileContent)
    {

        return $this->extractSubstringBetween($fileContent,'<!-- INICIO DO TEXTO -->', '<!-- FIM DO TEXTO -->');

    }

    public function getSuttaIndexFromFileName($fileName)
    {
        $name = str_replace('.php','',$fileName);
        $name = str_replace('./files_to_migrate/','',$name);
        return $name;
    }


    public function getSuttaCollectionFromIndex($index)
    {
        $collection = substr($index, 0, 2);

        return $collection;
    }


    public function getClearFileList($dir)
    {
        $listFiles = array_diff(scandir($dir), array('..', '.'));
        return $listFiles;
    }

    public function removeNextSuttaLink($text)
    {

        $tag = '<p class=Normal><a href=';

        // Find the position of the tag
        $tagPosition = strpos($text, $tag);

        // Extract the text before the tag
        if ($tagPosition !== false) {
            $extractedText = substr($text, 0, $tagPosition);
        } else {
            $extractedText = $text;
        }

        return $extractedText;
    }


    public function getTitleFromText($text)
    {
    // $text = str_replace("'","\'",$title);


        $text1 = $this->extractSubstringBetween($text ,'class=Tit1', 'Tit1>');

        //echo '---'.$text;
        $title = $this->extractSubstringBetween($text1 ,'>', '<');

        //if(!$title) echo '?????:'.$text;
        //echo '--2-'.$title;

        return $title;
    }


    function extractSubstringBetween($text, $string1, $string2) {
        $startPos = strpos($text, $string1);
        if ($startPos !== false) {
            $startPos += strlen($string1);
            $endPos = strpos($text, $string2, $startPos);
            if ($endPos !== false) {
                return substr($text, $startPos, $endPos - $startPos);
            }
        }
        return false;
    }

    function extractLastSubstringBetween($text, $string1, $string2) {
        $endPos = strrpos($text, $string2);
        if ($endPos !== false) {
            $startPos = strrpos($text, $string1, $endPos - strlen($text));
            if ($startPos !== false) {
                $startPos += strlen($string1);
                return substr($text, $startPos, $endPos - $startPos);
            }
        }
        return false;
    }



    public function correctEncoding($text)
    {
        //return mb_convert_encoding($text, 'UTF-8', mb_list_encodings());

        $originalText = $text;
        $convertedText = iconv('ISO-8859-1', 'UTF-8', $originalText);

        return $convertedText;
    }

    function truncateString($string, $length, $dots = "...") {
        return (strlen($string) > $length) ? substr($string, 0, $length - strlen($dots)) . $dots : $string;
    }



    ///=================  Old

    /**
     * =======================================================
     *
     *
     *
     * =======================================================
     */

    public function formatDataToinsertDB($data)
{
    $data['title'] = cutHtmlFromTitle( $data['title']);
    $data['name'] =  formatTextQuotesToSQL( $data['name']);
    $data['text'] =  formatTextQuotesToSQL( $data['text']);

    return $data;
}



public function getSuttaCollection($fileName)
{
    $index = getSuttaIndex($fileName);
    $collection = substr($index, 0, 2);

    return $collection;
}




public function getFileContent($filePath)
{
    $file = $filePath;
    $orig = file_get_contents($file);

    return correctEncoding($orig);
}

public function getIndex($html)
{
    preg_match("/<p class=Tit3 align=center style='text-align:center'><b>(.*?)<\/b>/s", $html, $match);

    return($match[0]);
}

public function getTitle($html)
{
    preg_match("/<p class=Tit1 align=center style='text-align:center'>(.*?)<\/Tit1>/s", $html, $match);

    return($match[0]);
}


// public function getTranslatedTitle($html)
// {
//     preg_match_all("/<p class=Tit1 align=center style='text-align:center'>(.*?)<\/Tit1>/s", $html, $match);

//     return($match[0][1]);
// }

public function getSuttaBody($html)
{
    preg_match('/<hr size=2 width="100%" align=center>(.*?)<hr size=2 width/s', $html, $match);


    $str =  str_replace('<hr size=2 width="100%" align=center>','',$match[0]);
    $str = str_replace('<hr size=2 width','',$str);

    return $str;

    //return($match[0]);
}


public function cutHtmlFromTitle($title)
{
   // $text = str_replace("'","\'",$title);

    $title = str_replace("<p class=Tit1 align=center style='text-align:center'> ","",$title);
    $title = str_replace(" </Tit1>","",$title);

    return $title;

}

public function formatTextQuotesToSQL($text)
{
    $text = str_replace("'","\'",$text);
   // $text =  str_replace('"','\"',$text);

    return $text;
}

}


