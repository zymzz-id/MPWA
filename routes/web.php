<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redirect;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Alexusmai\LaravelFileManager\Controllers\FileManagerController;

use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\ManageUsersController;
use App\Http\Controllers\Admin\PickindexController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\PluginController;

use App\Http\Controllers\User\BlastController;
use App\Http\Controllers\User\CampaignController;
use App\Http\Controllers\User\ContactController;
use App\Http\Controllers\User\DevicesController;
use App\Http\Controllers\User\MessagesController;
use App\Http\Controllers\User\MessagesHistoryController;
use App\Http\Controllers\User\PasswordResetController;
use App\Http\Controllers\User\RegisterController;
use App\Http\Controllers\User\RestapiController;
use App\Http\Controllers\User\ScanController;
use App\Http\Controllers\User\ShowMessageController;
use App\Http\Controllers\User\TagController;
use App\Http\Controllers\User\TwoFactorController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\User\NotificationController as UserNotificationController;

use App\Http\Controllers\Home\HomeController;
use App\Http\Controllers\Home\IndexController;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;

require_once 'custom-route.php';

Route::group(['prefix' => LaravelLocalization::setLocale()], function() {
	if(env("ENABLE_INDEX") == 'no'){
		Route::get('/', function()
		{
			return Redirect::to( '/login');
		});
	}else{
		Route::get('/',[IndexController::class,'index'])->name('index');
	}

	Route::middleware('2fa')->group(function (){
		Route::get('/2fa', [TwoFactorController::class, 'showVerify'])->name('2fa.verify');
		Route::post('/2fa', [TwoFactorController::class, 'verifyLogin'])->name('2fa.verify');
	});

	Route::middleware('auth', '2fa')->group(function (){
		Route::group(['prefix' => 'file-manager'], function () {
			Route::get('/', function(){return view('theme::pages.file-manager');})->name('file-manager');
            Route::get('initialize', [FileManagerController::class, 'initialize'])->name('fm.initialize');
			Route::get('content', [FileManagerController::class, 'content'])->name('fm.content');
			Route::get('tree', [FileManagerController::class, 'tree'])->name('fm.tree');
			Route::get('select-disk', [FileManagerController::class, 'selectDisk'])->name('fm.select-disk');
			Route::post('upload', [FileManagerController::class, 'upload'])->name('fm.upload');
			Route::post('delete', [FileManagerController::class, 'delete'])->name('fm.delete');
			Route::post('paste', [FileManagerController::class, 'paste'])->name('fm.paste');
			Route::post('rename', [FileManagerController::class, 'rename'])->name('fm.rename');
			Route::get('download', [FileManagerController::class, 'download'])->name('fm.download');
			Route::get('thumbnails', [FileManagerController::class, 'thumbnails'])->name('fm.thumbnails');
			Route::get('preview', [FileManagerController::class, 'preview'])->name('fm.preview');
			Route::get('url', [FileManagerController::class, 'url'])->name('fm.url');
			Route::post('create-directory', [FileManagerController::class, 'createDirectory'])->name('fm.create-directory');
			Route::post('create-file', [FileManagerController::class, 'createFile'])->name('fm.create-file');
			Route::post('update-file', [FileManagerController::class, 'updateFile'])->name('fm.update-file');
			Route::get('stream-file', [FileManagerController::class, 'streamFile'])->name('fm.stream-file');
			Route::post('zip', fn() => abort(403))->name('fm.zip');
			Route::post('unzip', fn() => abort(403))->name('fm.unzip');
			Route::get('ckeditor', [FileManagerController::class, 'ckeditor'])->name('fm.ckeditor');
			Route::get('tinymce', [FileManagerController::class, 'tinymce'])->name('fm.tinymce');
			Route::get('tinymce5', [FileManagerController::class, 'tinymce5'])->name('fm.tinymce5');
			Route::get('summernote', [FileManagerController::class, 'summernote'])->name('fm.summernote');
			Route::get('fm-button', [FileManagerController::class, 'fmButton'])->name('fm.fm-button');
        });

		Route::get('/home',[HomeController::class,'index'])->name('home');

		Route::get('/devices',[DevicesController::class,'index'])->name('devices');
		Route::post('/devices/setSessionSelectedDevice',[DevicesController::class,'setSelectedDeviceSession'])->name('setSessionSelectedDevice');
		Route::post('/devices/sethook',[DevicesController::class,'setHook'])->name('setHook');
		Route::post('/devices/setavailable',[DevicesController::class,'setAvailable'])->name('setAvailable');
		Route::post('/devices/setdelay',[DevicesController::class,'setDelay'])->name('setDelay');
		Route::post('/devices/sethookread',[DevicesController::class,'setHookRead'])->name('setHookRead');
		Route::post('/devices/sethookfull',[DevicesController::class,'setHookFull'])->name('setHookFull');
		Route::post('/devices/sethookreject',[DevicesController::class,'setHookReject'])->name('setHookReject');
		Route::post('/devices/sethooktyping',[DevicesController::class,'setHookTyping'])->name('setHookTyping');
		Route::post('/devices/setGPT',[DevicesController::class,'setGPT'])->name('setGPT');
		Route::post('/devices',[DevicesController::class,'store'])->name('addDevice');
		Route::delete('/devices',[DevicesController::class,'destroy'])->name('deleteDevice');

		Route::get('/scan/{number:body}',[ScanController::class,'scan'])->name('scan');
		Route::get('/code/{number:body}',[ScanController::class,'code'])->name('connect-via-code');

		Route::get('/phonebook',[TagController::class,'index'])->name('phonebook');
		Route::get('/get-phonebook',[TagController::class,'getPhonebook'])->name('getPhonebook');
		Route::delete('/clear-phonebook',[TagController::class,'clearPhonebook'])->name('clearPhonebook');
		Route::get('get-contact/{id}',[ContactController::class,'getContactByTagId']);
		Route::post('/contact/store',[ContactController::class,'store'])->name('contact.store');
		Route::delete('/contact/delete/{contact:id}',[ContactController::class,'destroy'])->name('contact.delete');
		Route::delete('/contact/delete-all/{id}',[ContactController::class,'DestroyAll'])->name('deleteAll');
		Route::post('/contact/import',[ContactController::class,'import'])->name('import');
		Route::get('/contact/export/{id}',[ContactController::class,'export'])->name('exportContact')->withoutMiddleware([LocaleCookieRedirect::class]);

		Route::post('/tags',[TagController::class,'store'])->name('tag.store');
		Route::delete('/tags',[TagController::class,'destroy'])->name('tag.delete');
		Route::post('fetch-groups',[TagController::class ,'fetchGroups'])->name('fetch.groups');

		Route::get('/campaigns',[CampaignController::class,'index'])->name('campaigns')->middleware('permissions');
		Route::get('/campaign/create',[CampaignController::class,'create'])->name('campaign.create')->middleware('permissions');
		Route::post('/campaign/store',[CampaignController::class,'store'])->name('campaign.store')->middleware('permissions');
		Route::post('/campaign/pause/{id}',[CampaignController::class,'pause'])->name('campaign.pause')->middleware('permissions');
		Route::post('/campaign/resume/{id}',[CampaignController::class,'resume'])->name('campaign.resume')->middleware('permissions');
		Route::delete('/campaign/delete/{id}',[CampaignController::class,'destroy'])->name('campaign.delete')->middleware('permissions');
		Route::get('/campaign/show/{id}',[CampaignController::class,'show'])->name('campaign.show')->middleware('permissions');
		Route::delete('/campaign/clear',[CampaignController::class,'destroyAll'])->name('campaigns.delete.all')->middleware('permissions');
		Route::get('/campaign/blast/{campaign:id}',[BlastController::class,'index'])->name('campaign.blasts')->middleware('permissions');

		Route::post('/preview-message',[ShowMessageController::class,'index'])->name('previewMessage');
		Route::get('/form-message/{type}',[ShowMessageController::class,'getFormByType'])->name('formMessage');
		Route::get('/form-message-edit/{type}',[ShowMessageController::class,'showEdit'])->name('formMessageEdit');

		Route::get('/message/test',[MessagesController::class,'index'])->name('messagetest');
		Route::post('/message/test',[MessagesController::class,'store'])->name('messagetest')->middleware('permissions');
		Route::get('/fetch-whatsapp-product', [MessagesController::class, 'fetchWhatsAppProduct'])->name('fetch.whatsapp.product');
		Route::post('fetch-channel',[MessagesController::class ,'fetchChannel'])->name('fetch.channel');

		Route::get('/api-docs',RestapiController::class)->name('rest-api')->middleware('permissions');

		Route::get('/user/settings',[UserController::class,'settings'])->name('user.settings');
		Route::post('/user/change-password',[UserController::class,'changePasswordPost'])->name('changePassword');
		Route::post('/user/setting/apikey',[UserController::class,'generateNewApiKey'])->name('generateNewApiKey');
		Route::post('/user/setting/deletehistory',[UserController::class,'deleteHistory'])->name('deleteHistory');

		Route::post('/settings/timezone', [UserController::class, 'updateTimezone'])->name('user.settings.timezone');

		Route::post('/user/settings/2fa', [UserController::class, 'toggleTwoFactor'])->name('user.settings.2fa');
		Route::get('/user/2fa_setup', [TwoFactorController::class, 'showSetup'])->name('user.2fa_setup');
		Route::post('/user/2fa/verify', [TwoFactorController::class, 'verify'])->name('user.2fa.verify');

		Route::post('/user/notification-seen', [UserNotificationController::class, 'markAsSeen'])->name('user.notification.seen');

		Route::get('/admin/settings',[SettingController::class,'index'])->name('admin.settings')->middleware('admin');
		Route::post('/settings/server',[SettingController::class,'setServer'])->name('setServer')->middleware('admin');
		Route::post('/settings/generate-ssl', [SettingController::class, 'generateSslCertificate'])->name('generateSsl')->middleware('admin');
		Route::post('/settings/setenvall', [SettingController::class, 'setEnvAll'])->name('setEnvAll')->middleware('admin');
		Route::post('/settings/registration', [SettingController::class, 'setRegistration'])->name('settings.registration')->middleware('admin');

		Route::get('/admin/cronjob',[SettingController::class,'cronJob'])->name('cronjob')->middleware('admin');

		Route::get('/admin/pickindex', [PickindexController::class, 'editSettings'])->name('admin.index.edit')->middleware('admin');
		Route::post('/admin/pickindex', [PickindexController::class, 'updateSettings'])->name('admin.index.update')->middleware('admin');
		Route::post('/admin/pickindexcolor', [PickindexController::class, 'updateColor'])->name('admin.index.color')->middleware('admin');
		Route::post('/admin/pickindexenable', [PickindexController::class, 'enableIndex'])->name('admin.index.enable')->middleware('admin');
		Route::post('/admin/pickindexconfig', [PickindexController::class, 'updateConfigOptions'])->name('admin.index.config.update')->middleware('admin');

		Route::get('/admin/languages', [LanguageController::class, 'index'])->name('languages.index')->middleware('admin');
		Route::get('/admin/languages/{lang}/edit', [LanguageController::class, 'edit'])->name('languages.edit')->middleware('admin');
		Route::post('/admin/languages/{lang}', [LanguageController::class, 'update'])->name('languages.update')->middleware('admin');
		Route::post('/admin/languages/add/new', [LanguageController::class, 'add'])->name('languages.add')->middleware('admin');
		Route::delete('/admin/languages/{lang}', [LanguageController::class, 'destroy'])->name('languages.destroy')->middleware('admin');

		Route::get('/admin/update',[UpdateController::class,'checkUpdate'])->name('update')->middleware('admin');
		Route::post('/admin/update/install',[UpdateController::class,'installUpdate'])->name('update.install')->middleware('admin');

		Route::get('/admin/manage-users',[ManageUsersController::class,'index'])->name('admin.manage-users')->middleware('admin');
		Route::post('/admin/user/store',[ManageUsersController::class,'store'])->name('user.store')->middleware('admin');
		Route::post('/admin/user/updatePlan/{id}',[ManageUsersController::class,'updatePlan'])->name('admin.users.updatePlan')->middleware('admin');
		Route::delete('/admin/user/delete/{id}',[ManageUsersController::class,'delete'])->name('user.delete')->middleware('admin');
		Route::get('admin/user/edit',[ManageUsersController::class,'edit'])->name('user.edit')->middleware('admin');
		Route::post('admin/user/update',[ManageUsersController::class,'update'])->name('user.update')->middleware('admin');

		Route::get('/login-as-user/{id}', [ManageUsersController::class, 'loginAsUser'])->name('admin.loginAsUser')->middleware('admin');
		Route::get('/back-to-admin', [ManageUsersController::class, 'backToAdmin'])->name('admin.backToAdmin')->middleware('admin');

		Route::get('/messages-history',[MessagesHistoryController::class,'index'])->name('messages.history');
		Route::post('/resend-message',[MessagesHistoryController::class,'resend'])->name('resend.message');
		Route::post('/delete-messages',[MessagesHistoryController::class,'deleteAll'])->name('delete.messages');

		Route::get('/admin/plugins', [PluginController::class, 'index'])->name('admin.plugins.index')->middleware('admin');
		Route::post('/admin/plugins/upload', [PluginController::class, 'upload'])->name('admin.plugins.upload')->middleware('admin');
		Route::post('/admin/plugins/replace-confirm', [PluginController::class, 'replaceConfirm'])->name('admin.plugins.replace-confirm')->middleware('admin');
		Route::get('/admin/plugins/marketplace', [PluginController::class, 'marketplace'])->name('admin.plugins.marketplace')->middleware('admin');
		Route::post('/admin/plugins/marketplace/install', [PluginController::class, 'marketplaceInstall'])->name('admin.plugins.marketplace.install')->middleware('admin');
		Route::post('/admin/plugins/{slug}/enable', [PluginController::class, 'enable'])->name('admin.plugins.enable')->middleware('admin');
		Route::post('/admin/plugins/{slug}/disable', [PluginController::class, 'disable'])->name('admin.plugins.disable')->middleware('admin');
		Route::delete('/admin/plugins/{slug}', [PluginController::class, 'destroy'])->name('admin.plugins.destroy')->middleware('admin');
		Route::get('/admin/plugins/{slug}/file/{filename}', [PluginController::class, 'pluginFile'])->name('admin.plugins.file')->middleware('admin');

		Route::get('/permission-denied', function () { return view('theme::pages.permission'); })->name('permission.denied');
	});

	Route::middleware('guest')->group(function(){
		Route::get('/login',[LoginController::class,'index'])->name('login');
		Route::get('/register',[RegisterController::class,'index'])->name('register');
		Route::post('/register',[RegisterController::class,'store'])->name('register');
		Route::post('/login',[LoginController::class,'store'])->name('login')->middleware('throttle:5,1');
		Route::get('password/reset', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
		Route::post('password/email', [PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
		Route::get('password/reset/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
		Route::post('password/reset', [PasswordResetController::class, 'reset'])->name('password.update');
	});
	Route::match(['get', 'post'], '/logout', LogoutController::class)->name('logout');
	Route::get('/install', [SettingController::class,'install'])->name('setting.install_app');
	Route::post('/install', [SettingController::class,'install'])->name('settings.install_app');

	Route::post('/settings/check_database_connection',[SettingController::class,'test_database_connection'])->name('connectDB');
	Route::post('/settings/activate_license',[SettingController::class,'activate_license'])->name('activateLicense');
});
