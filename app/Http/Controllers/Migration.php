<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

use App\Models\Post;
use App\Models\FailedFiles;
use App\Models\Collections;
use App\Models\Redirect;
use App\Models\wp_posts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use App\Models\wp_term_relationships;
use App\Models\wp_term_taxonomy;
use App\Models\wp_terms;
use Illuminate\Database\Eloquent\Builder;

class Migration extends Controller
{

    public function migrateWordpress()
    {



        /**
         * lembrar de dar update count taxonomy
         */

        echo '<h3>Migrating Posts to wordpress</h3>';

        $posts = Post::get()->all();


        $count = 0;

        foreach($posts as $post){

            $count++;

            $postTitle = $post['title_pali'] ?? $post['title_pt'] ;
            $postSlug = Str::slug($postTitle);
            $postContent =$post['text'];

            $postOldUrl = $post['old_url'];


            $wpPost = wp_posts::create([
                'post_author'=>1,
                'post_date'=>'2023-09-01 12:38:48',
                'post_date_gmt'=>'2023-09-01 12:38:48',
                'post_content'=>$postContent,
                'post_title'=>$postTitle,
                'post_excerpt'=>'',
                'post_status'=>'publish',
                'comment_status'=>'closed',
                'ping_status'=>'closed',
                'post_password'=>'',
                'post_name'=>$postSlug,
                'to_ping'=>'',
                'pinged'=>'',
                'post_modified'=>'2023-09-01 12:38:48',
                'post_modified_gmt'=>'2023-09-01 12:38:48',
                'post_content_filtered'=>'',
                'post_parent'=>0,
                'guid'=>'migration:'.$postSlug.'-'.Carbon::now(),
                'menu_order'=>0,
                'post_type'=>'post',
            ]);


            // a partir daqui insere as categorias e tags



            try{

                if($post['title_pali']) // nesse caso é um sutta
                {
                    $this->createRelation($wpPost['id'],'Suttas','category');
                    $this->createRelation($wpPost['id'],'Suttas','post_tag');

                    $colection = Collections::find( $post['collection_id'] );
                    $colectionName = $colection['full_name'] ?? $colection['name'];

                    $this->createRelation($wpPost['id'],$colectionName,'post_tag');
                }else
                {
                    $this->createRelation($wpPost['id'],'Textos Theravada','category');
                    $this->createRelation($wpPost['id'],'Textos Theravada','post_tag');
                }

                Redirect::create([
                    'old_url' => $postOldUrl,
                    'wp_posts_id'=> $wpPost['id']
                ]);


            }catch (QueryException $e) {
                // Log the error
                $error = $this->truncateString( $e->getMessage(), 220) ;

                echo 'erro: '.$error.'<br/>';
                //$this->errorLog($filePath, 'no insertion: '.$error);

            }

            if($count == 100) echo '100 ok <br/>';
            if($count == 200) echo '200 ok <br/>';
            if($count == 500) echo '500 ok <br/>';
            if($count == 1000) echo '1000 ok <br/>';
            if($count == 2000) echo '2000 ok <br/>';

        }


        echo '<h4>Finished.</h4>';

        return;

    }

    /**
     * relation may be:'category' or 'post_tag'
     */
    public function createRelation($wpPostId, $groupName, $relation)
    {

            $termTaxonomy = $this->getTermTaxonomy($groupName,$relation);

            if(!$termTaxonomy) $termTaxonomy = $this->createTermTaxonomy($groupName,$relation);

            $relation = $this->createTermRelationship($wpPostId, $termTaxonomy['term_taxonomy_id']);

            return $relation;
    }



    public function createTermRelationship($postId, $termTaxonomyId)
    {
        return wp_term_relationships::create([
                                        'object_id'=>$postId,
                                        'term_taxonomy_id'=>$termTaxonomyId
                                        ]);
    }

    public function createTermTaxonomy($name,$taxonomy)
    {
        $term = wp_terms::create([
                            'name'=> $name,
                            'slug'=> Str::slug($name)
                        ]);
        wp_term_taxonomy::create([
                            'term_id'=>$term['id'],
                            'taxonomy'=>$taxonomy,
                            'description' =>''
                        ]);

        $termTaxonomy = wp_term_taxonomy::query()
                                        ->where('term_id',$term['id'])
                                        ->where('taxonomy',$taxonomy)
                                        ->get()
                                        ->first();


        /**
         * aqui temos uma clássica gambiarra
         * wp nomeia a id como term_taxonomy_id
         * mas quando esse campo vem do create ele vem nomado 'id'
         * quando vem do get, vem 'term_taxonomy_id'
         * por isso retornamos o get, não o create - para padronização
         */

        return $termTaxonomy;
    }

    public function getTermTaxonomy($name,$taxonomy)
    {

        //as relações em wp são dificeis de serem setadas no laravel
        //já que o id das tabelas não obedece o mesmo padrão

        //get terms with 'name'
            //check if any of the terms has taxonomy searched

            $terms = wp_terms::query()
                            ->where('name',$name)
                            ->get();

        $match = [];

        if($terms)
            foreach($terms as $term)
                $match = wp_term_taxonomy::query()
                                    ->where('term_id', $term['term_id'])
                                    ->where('taxonomy', $taxonomy)
                                    ->get()
                                    ->first();

        if($match) return $match;

        //if positive update count & return taxonomy found

        //if none: create term + create taxonomy;
            //update count & return new term_taxonomy_id

    }


    public function migrateCategory($postWpId, $oldCategory)
    {

        //create category + relation post-category


        //create tag + create relation post category





        //1-create term

        //2-create term taxonomy

        //3-create term relationship

    }



    public function migrateDatabase()
    {


        // $folderSuttas = 'sutta/';


        // $folderTexts = 'arquivo_textos_theravada/';

        $folderSuttas = 'test_sutta/';


        $folderTexts = 'test_textos/';

        echo 'started...<br/><br/>';

        echo 'Suttas: <br/>';

        $this->migrateSuttasStage1($folderSuttas);

        echo 'Textos: <br/>';

        $this->migrateTextsStage1($folderTexts);

        echo '<h3> Here we are done </h3>';

        //echo 'Agora olhe no banco de dados e dê o nome apropriado para cada coleção';

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

            $collectionId = $this->getCollectionId($suttaCollection);

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

    private function debug($data)
    {
        echo '<pre>';
        //print_r($data);
        echo json_encode($data);
        echo '<pre>';
    }

}


