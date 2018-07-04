<?php

namespace barrelstrength\sproutemail\controllers;

use barrelstrength\sproutbase\app\email\base\Mailer;
use barrelstrength\sproutbase\app\email\base\EmailTemplateTrait;
use barrelstrength\sproutbase\SproutBase;
use barrelstrength\sproutemail\elements\CampaignEmail;
use barrelstrength\sproutemail\events\RegisterSendSproutEmailEvent;
use barrelstrength\sproutbase\app\email\models\Response;
use barrelstrength\sproutemail\models\CampaignType;
use barrelstrength\sproutemail\services\CampaignEmails;
use barrelstrength\sproutemail\SproutEmail;
use craft\helpers\ElementHelper;
use craft\helpers\UrlHelper;
use craft\web\assets\cp\CpAsset;
use craft\web\Controller;
use Craft;
use craft\web\View;
use yii\base\Exception;

class CampaignEmailController extends Controller
{
    use EmailTemplateTrait;
    
    /**
     * @var CampaignType
     */
    protected $campaignType;

    /**
     * Renders a Campaign Email Edit Page
     *
     * @param null               $campaignTypeId
     * @param CampaignEmail|null $campaignEmail
     *
     * @return \yii\web\Response
     */
    public function actionEditCampaignEmail($campaignTypeId = null, CampaignEmail $campaignEmail = null)
    {
        $emailId = Craft::$app->getRequest()->getSegment(4);

        // Check if we already have an Campaign Email route variable
        // If so it's probably due to a bad form submission and has an error object
        // that we don't want to overwrite.
        if (!$campaignEmail) {
            if (is_numeric($emailId)) {
                $campaignEmail = SproutEmail::$app->campaignEmails->getCampaignEmailById($emailId);
            } else {
                $campaignEmail = new CampaignEmail();
            }
        }

        if ($campaignTypeId == null) {
            $campaignTypeId = $campaignEmail->campaignTypeId;
        } else {
            $campaignEmail->campaignTypeId = $campaignTypeId;
        }

        $campaignType = SproutEmail::$app->campaignTypes->getCampaignTypeById($campaignTypeId);

        $campaignEmail->fieldLayoutId = $campaignType->fieldLayoutId;

        $showPreviewBtn = false;

        // Should we show the Share button too?
        if ($campaignEmail->id && $campaignEmail->getUrl()) {
            $showPreviewBtn = true;
        }

        $tabs = $this->getFieldLayoutTabs($campaignEmail);

        return $this->renderTemplate('sprout-base-email/campaigns/_edit', [
            'campaignEmail' => $campaignEmail,
            'emailId' => $emailId,
            'campaignTypeId' => $campaignTypeId,
            'campaignType' => $campaignType,
            'showPreviewBtn' => $showPreviewBtn,
            'tabs' => $tabs
        ]);
    }

    /**
     * Saves a Campaign Email
     *
     * @return null|\yii\web\Response
     * @throws Exception
     * @throws \Throwable
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSaveCampaignEmail()
    {
        $this->requirePostRequest();

        $campaignTypeId = Craft::$app->getRequest()->getBodyParam('campaignTypeId');

        $this->campaignType = SproutEmail::$app->campaignTypes->getCampaignTypeById($campaignTypeId);

        if (!$this->campaignType) {
            throw new Exception(Craft::t('sprout-email', 'No Campaign exists with the id “{id}”', [
                'id' => $campaignTypeId
            ]));
        }

        $campaignEmail = $this->getCampaignEmailModel();

        $campaignEmail = $this->populateCampaignEmailModel($campaignEmail);

        if (Craft::$app->getRequest()->getBodyParam('saveAsNew')) {
            $campaignEmail->saveAsNew = true;
            $campaignEmail->id = null;
        }

        $session = Craft::$app->getSession();

        if ($session AND SproutEmail::$app->campaignEmails->saveCampaignEmail($campaignEmail, $this->campaignType)) {
            $session->setNotice(Craft::t('sprout-email', 'Campaign Email saved.'));
        } else {
            $session->setError(Craft::t('sprout-email', 'Could not save Campaign Email.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'campaignEmail' => $campaignEmail
            ]);

            return null;
        }

        return $this->redirectToPostedUrl($campaignEmail);
    }

    /**
     * Sends a Campaign Email
     *
     * @return \yii\web\Response
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSendCampaignEmail()
    {
        $this->requirePostRequest();

        $emailId = Craft::$app->getRequest()->getParam('emailId');
        $campaignType = null;
        /**
         * @var $campaignEmail CampaignEmail
         */
        $campaignEmail = SproutEmail::$app->campaignEmails->getCampaignEmailById($emailId);

        if ($campaignEmail) {
            $campaignType = SproutEmail::$app->campaignTypes->getCampaignTypeById($campaignEmail->campaignTypeId);
        }

        if ($campaignEmail && $campaignType) {
            try {
                $response = SproutEmail::$app->mailers->sendCampaignEmail($campaignEmail, $campaignType);

                if ($response instanceof Response) {
                    if ($response->success == true) {
                        if ($response->emailModel != null) {
                            $emailModel = $response->emailModel;

                            $this->trigger(CampaignEmails::EVENT_SEND_SPROUTEMAIL, new RegisterSendSproutEmailEvent([
                                'campaignEmail' => $campaignEmail,
                                'emailModel' => $emailModel,
                                'campaign' => $campaignType
                            ]));
                        }
                    }

                    return $this->asJson($response);
                }

                $errorMessage = Craft::t('sprout-email', 'Mailer did not return a valid response model after sending Campaign Email.');

                if (!$response) {
                    $errorMessage = Craft::t('sprout-email', 'Unable to send email.');
                }

                return $this->asJson(
                    Response::createErrorModalResponse(
                        'sprout-base-email/_modals/response',
                        [
                            'email' => $campaignEmail,
                            'campaign' => $campaignType,
                            'message' => Craft::t('sprout-email', $errorMessage),
                        ]
                    )
                );
            } catch (\Exception $e) {

                Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_CP);

                return $this->asJson(
                    Response::createErrorModalResponse(
                        'sprout-base-email/_modals/response',
                        [
                            'email' => $campaignEmail,
                            'campaign' => $campaignType,
                            'message' => Craft::t('sprout-email', $e->getMessage()),
                        ]
                    )
                );
            }
        }

        return $this->asJson(
            Response::createErrorModalResponse(
                'sprout-base-email/_modals/response',
                [
                    'email' => $campaignEmail,
                    'campaign' => !empty($campaignType) ? $campaignType : null,
                    'message' => Craft::t('sprout-email', 'The campaign email you are trying to send is missing.'),
                ]
            )
        );
    }

    /**
     * Renders the Send Test Campaign Email Modal
     *
     * @return \yii\web\Response
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionPrepareTestCampaignEmailModal()
    {
        $this->requirePostRequest();

        $emailId = Craft::$app->getRequest()->getBodyParam('emailId');
        $campaignEmail = SproutEmail::$app->campaignEmails->getCampaignEmailById($emailId);

        $campaignType = null;

        if ($campaignEmail != null) {
            $campaignType = SproutEmail::$app->campaignTypes->getCampaignTypeById($campaignEmail->campaignTypeId);
        }

        $html = Craft::$app->getView()->renderTemplate('sprout-base-email/_modals/campaigns/prepare-test-email', [
            'campaignEmail' => $campaignEmail,
            'campaignType' => $campaignType
        ]);

        return $this->asJson([
            'success' => true,
            'content' => $html
        ]);
    }

    /**
     * Renders the Schedule Campaign Email Modal
     *
     * @return \yii\web\Response
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionPrepareScheduleCampaignEmail()
    {
        $this->requirePostRequest();

        $emailId = Craft::$app->getRequest()->getBodyParam('emailId');

        $campaignType = null;

        $campaignEmail = SproutEmail::$app->campaignEmails->getCampaignEmailById($emailId);

        if ($campaignEmail != null) {
            $campaignType = SproutEmail::$app->campaignTypes->getCampaignTypeById($campaignEmail->campaignTypeId);
        }

        $html = Craft::$app->getView()->renderTemplate('sprout-base-email/_modals/campaigns/prepare-scheduled-email', [
            'campaignEmail' => $campaignEmail,
            'campaignType' => $campaignType
        ]);

        return $this->asJson([
            'success' => true,
            'content' => $html
        ]);
    }

    /**
     * Renders the Shared Campaign Email
     * @param null $emailId
     * @param null $type
     *
     * @throws Exception
     * @throws \HttpException
     * @throws \ReflectionException
     * @throws \yii\base\ExitException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionViewSharedCampaignEmail($emailId = null, $type = null)
    {
        $this->requireToken();

        if ($campaignEmail = SproutEmail::$app->campaignEmails->getCampaignEmailById($emailId)) {
            $campaignType = SproutEmail::$app->campaignTypes->getCampaignTypeById($campaignEmail->campaignTypeId);

            $params = [
                'email' => $campaignEmail,
                'campaignType' => $campaignType
            ];

            $extension = ($type != null && $type == 'text') ? 'txt' : 'html';

            $content = $this->getHtmlBody($campaignEmail, $params, $campaignType);

            SproutEmail::$app->campaignEmails->showCampaignEmail($content, $extension);
        }

        throw new \HttpException(404);
    }

    /**
     * Sends a Test Campaign Email
     *
     * Test Emails do not trigger an onSendEmail event and do not get marked as Sent.
     *
     * @return \yii\web\Response
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSendTestCampaignEmail()
    {
        $this->requirePostRequest();

        $emailId = Craft::$app->getRequest()->getBodyParam('emailId');

        $campaignType = null;

        $campaignEmail = SproutEmail::$app->campaignEmails->getCampaignEmailById($emailId);

        if ($campaignEmail) {
            $campaignType = SproutEmail::$app->campaignTypes->getCampaignTypeById($campaignEmail->campaignTypeId);
        }

        $errorMsg = '';

        $recipients = Craft::$app->getRequest()->getBodyParam('recipients');

        if ($recipients === null) {
            $errorMsg = Craft::t('sprout-email', 'Empty recipients.');
        }

        $result = $this->getValidAndInvalidRecipients($recipients);

        $invalidRecipients = $result['invalid'];
        $emails = $result['emails'];

        if (!empty($invalidRecipients)) {
            $invalidEmails = implode('<br/>', $invalidRecipients);

            $errorMsg = Craft::t('sprout-email', 'The following recipient email addresses do not validate: {invalidEmails}', [
                'invalidEmails' => $invalidEmails
            ]);
        }

        if (!empty($errorMsg)) {
            $asJson = Response::createErrorModalResponse('sprout-base-email/_modals/response', [
                'email' => $campaignEmail,
                'message' => $errorMsg
            ]);

            return $this->asJson($asJson);
        }

        try {
            /**
             * @var $mailer Mailer
             */
            $mailer = $campaignEmail->getMailer();

            $response = null;

            Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_SITE);

            if (empty($campaignType->template)) {
                $campaignType->template = SproutBase::$app->sproutEmail->getEmailTemplates();
            }

            if ($mailer) {
                $response = $mailer->sendTestCampaignEmail($campaignEmail, $campaignType, $emails);
            }

            Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_CP);

            if ($response instanceof Response) {

                $response->content = Craft::$app->getView()->renderTemplate('sprout-base-email/_modals/response', [
                    'email' => $campaignEmail,
                    'success' => $response->success,
                    'message' => $response->message
                ]);

                if ($response->success == true) {
                    return $this->asJson($response);
                }
            }

            $errorMessage = Craft::t('sprout-email', 'Mailer did not return a valid response model after sending Campaign Email.');

            if (!$response) {
                $errorMessage = Craft::t('sprout-email', 'Unable to send email.');
            }

            if ($response->message) {
                $errorMessage .= ' '.$response->message;
            }

            return $this->asJson(
                Response::createErrorModalResponse(
                    'sprout-base-email/_modals/response',
                    [
                        'email' => $campaignEmail,
                        'campaign' => $campaignType,
                        'message' => Craft::t('sprout-email', $errorMessage),
                    ]
                )
            );
        } catch (\Exception $e) {

            Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_CP);

            return $this->asJson(
                Response::createErrorModalResponse(
                    'sprout-base-email/_modals/response',
                    [
                        'email' => $campaignEmail,
                        'campaign' => $campaignType,
                        'message' => Craft::t('sprout-email', $e->getMessage()),
                    ]
                )
            );
        }
    }

    /**
     * @param null   $emailId
     * @param string $type
     *
     * @return \yii\web\Response
     * @throws \HttpException
     */
    public function actionShareCampaignEmail($emailId = null, $type = 'html')
    {
        if ($emailId) {
            $campaignEmail = SproutEmail::$app->campaignEmails->getCampaignEmailById($emailId);

            if (!$campaignEmail) {
                throw new \HttpException(404);
            }
        } else {
            throw new \HttpException(404);
        }

        $params = [
            'emailId' => $emailId,
            'type' => $type
        ];

        // Create the token and redirect to the entry URL with the token in place
        $token = Craft::$app->getTokens()->createToken(['sprout-email/campaign-email/view-shared-campaign-email', $params]);

        $emailUrl = '';
        if (!empty($campaignEmail->getUrl())) {
            $emailUrl = $campaignEmail->getUrl();
        }

        $url = UrlHelper::urlWithToken($emailUrl, $token);

        return $this->redirect($url);
    }

    /**
     * @param null $emailType
     * @param null $emailId
     *
     * @return \yii\web\Response
     * @throws \yii\base\InvalidConfigException
     */
    public function actionPreviewCampaignEmail($emailType = null, $emailId = null)
    {
        Craft::$app->getView()->registerAssetBundle(CpAsset::class);

        return $this->renderTemplate('sprout-base-email/_special/preview', [
            'emailType' => $emailType,
            'emailId' => $emailId
        ]);
    }

    /**
     * Returns a Campaign Email Model
     *
     * @return CampaignEmail|\craft\base\ElementInterface|null
     * @throws \Exception
     */
    protected function getCampaignEmailModel()
    {
        $emailId = Craft::$app->getRequest()->getBodyParam('emailId');
        $saveAsNew = Craft::$app->getRequest()->getBodyParam('saveAsNew');

        if ($emailId && !$saveAsNew && $emailId !== 'new') {
            $campaignEmail = SproutEmail::$app->campaignEmails->getCampaignEmailById($emailId);

            if (!$campaignEmail) {
                throw new Exception(Craft::t('sprout-email', 'No entry exists with the ID “{id}”', ['id' => $emailId]));
            }
        } else {
            $campaignEmail = new CampaignEmail();
        }

        return $campaignEmail;
    }

    /**
     * Populates a Campaign Email Model
     *
     * @param CampaignEmail $campaignEmail
     *
     * @return CampaignEmail
     */
    protected function populateCampaignEmailModel(CampaignEmail $campaignEmail)
    {
        $campaignEmail->campaignTypeId = $this->campaignType->id;
        $campaignEmail->slug = Craft::$app->getRequest()->getBodyParam('slug', $campaignEmail->slug);
        $campaignEmail->enabled = (bool)Craft::$app->getRequest()->getBodyParam('enabled', $campaignEmail->enabled);
        $campaignEmail->fromName = Craft::$app->getRequest()->getBodyParam('sproutEmail.fromName');
        $campaignEmail->fromEmail = Craft::$app->getRequest()->getBodyParam('sproutEmail.fromEmail');
        $campaignEmail->replyToEmail = Craft::$app->getRequest()->getBodyParam('sproutEmail.replyToEmail');
        $campaignEmail->subjectLine = Craft::$app->getRequest()->getBodyParam('subjectLine');
        $campaignEmail->dateScheduled = Craft::$app->getRequest()->getBodyParam('dateScheduled');
        $campaignEmail->defaultBody = Craft::$app->getRequest()->getBodyParam('defaultBody');

        if (Craft::$app->getRequest()->getBodyParam('sproutEmail.recipients') != null) {
            $campaignEmail->recipients = Craft::$app->request->getBodyParam('sproutEmail.recipients');
        }

        $enableFileAttachments = Craft::$app->request->getBodyParam('sproutEmail.enableFileAttachments');
        $campaignEmail->enableFileAttachments = $enableFileAttachments ?: false;

        $campaignEmail->title = $campaignEmail->subjectLine;

        if ($campaignEmail->slug === null) {
            $campaignEmail->slug = ElementHelper::createSlug($campaignEmail->subjectLine);
        }

        $fieldsLocation = Craft::$app->getRequest()->getParam('fieldsLocation', 'fields');

        $campaignEmail->setFieldValuesFromRequest($fieldsLocation);

        $campaignEmail->listSettings = Craft::$app->getRequest()->getBodyParam('lists');

        return $campaignEmail;
    }
}