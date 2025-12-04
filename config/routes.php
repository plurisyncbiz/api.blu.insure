<?php

use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use App\Middleware\BearerAuthMiddleware;

return function (App $app) {
    $app->options('/{routes:.+}', function ($request, $response, $args) {
        return $response;
    });
    $app->get('/', \App\Actions\HomeAction::class);
    $app->get('/token', \App\Actions\Token\TokenCreateAction::class);
    $app->group('/users', function (Group $group){
        $group->get('/', \App\Actions\User\UserAction::class);
        $group->get('/{id}', \App\Actions\User\ViewUserAction::class);
    });
    $app->group('/serials', function (Group $group){
        $group->get('/', \App\Actions\Serials\ViewSerialsAction::class);
        $group->post('/file/{filename}', \App\Actions\Serials\AddSerialsFromFile::class);
    });
    $app->group('/serial', function (Group $group){
        $group->get('/{serialno}', \App\Actions\Serials\ViewSerialAction::class);
        $group->post('/', \App\Actions\Serials\AddSerialAction::class);
    });
    $app->group('/activate', function (Group $group){
        $group->post('/', \App\Actions\Activate\ActivateAction::class);
        $group->post('/sms', \App\Actions\Activate\ActivateSms::class);
        $group->get('/{id}', \App\Actions\Activate\FetchActivationAction::class);
    });
    $app->group('/mandate', function (Group $group){
        $group->post('/{activationid}', \App\Actions\Payment\AddMandateAction::class);
    });
    $app->group('/sms', function (Group $group){
        $group->post('/', \App\Actions\SendSmsAction::class);
    });
    $app->group('/policy', function (Group $group){
        $group->get('/payload/{id}', \App\Actions\Policy\CreatePolicyPayloadAction::class);
        $group->get('/details/{id}', \App\Actions\Policy\GetPolicyInfoAction::class);
        $group->get('/upload', \App\Actions\UploadFileAction::class);
        $group->post('/main', \App\Actions\Policy\AddMainLifeAction::class);
        $group->post('/spouse', \App\Actions\Policy\AddSpouseLifeAction::class);
        $group->post('/beneficiary', \App\Actions\Policy\AddBeneficiaryAction::class);
        $group->post('/payment', \App\Actions\Policy\AddPaymentAction::class);
    });

};