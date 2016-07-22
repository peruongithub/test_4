<?php

namespace components;


use trident\phpRender;

class PagesTriad extends MainTriad
{
    /**
     * @return array|void
     */
    public function index(){
        $page = $this->request->param('page',null);
        if(null === $page){
            $this->response->status(404);
            return '';
        }

        $fileName = "./data/tpl/pages/$page.tpl.php";

        $config = $this->getActionConfig(__METHOD__);
        /**
         * @var $render phpRender
         */
        $render = $this->getRender($config);

        try{
            $content = $render->fetch($fileName,[],$this);
        }catch (\Exception $e){
            // log
            $this->response->status(404);
            return '';
        }

        return [
            'htmlTitle' => str_replace(['-','_'],' ',ucfirst($page)),
            'mainContent' => $content
        ];
    }
}