<?php
namespace components;

use trident\DI;

class InviteTriad extends MainTriad
{
    /**
     * @var $model InviteModel
     */
    protected $model;

    protected $actions = [
        'index' => [
            'httpMethods' => 'GET',
        ],
        'checkInvite' => [
            'filters' => ['onlyAjaxAllowed' => [__CLASS__, 'onlyAjaxAllowed']],
        ]
    ];

    public function index()
    {
        if($this->request->is_ajax()){
            return $this->model->simple($this->request);
        }

        return ['mainContent' => DI::get('render')->fetch('./data/tpl/invites.tpl.php', $this->model->getDefaults(), $this)];
    }

    public function checkInvite(){
        $valid = $this->model->isValidInvite($this->request, null);
        $message = '';
        if(
            $valid instanceof ModelResponse &&
            $valid->getStatus() === Status::FAILURE &&
            $valid->getAction() === Action::SELECT
        ){
            // log
            $valid = false;
        }elseif($valid instanceof ModelResponse){
            if($valid->getStatus() === Status::ERROR ||$valid->getStatus() === Status::FAILURE){
                //$this->response->status(400);
            }
            $message = $valid->getMessage();
            $valid = false;
        }

        return ['valid' => $valid,'message' =>$message];
    }
}