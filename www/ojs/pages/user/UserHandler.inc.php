<?php

/**
 * @file pages/user/UserHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserHandler
 * @ingroup pages_user
 *
 * @brief Handle requests for user functions.
 */

import('lib.pkp.pages.user.PKPUserHandler');

class UserHandler extends PKPUserHandler {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}

	/**
	 * Display user gifts page
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function gifts($args, $request) {
		$this->validate();

		$journal = $request->getJournal();
		if (!$journal) $request->redirect(null, 'dashboard');

		// Ensure gift payments are enabled
		import('classes.payment.ojs.OJSPaymentManager');
		$paymentManager = new OJSPaymentManager($request);
		$acceptGiftPayments = $paymentManager->acceptGiftPayments();
		if (!$acceptGiftPayments) $request->redirect(null, 'dashboard');

		$acceptGiftSubscriptionPayments = $paymentManager->acceptGiftSubscriptionPayments();
		$journalId = $journal->getId();
		$user = $request->getUser();
		$userId = $user->getId();

		// Get user's redeemed and unreedemed gift subscriptions
		$giftDao = DAORegistry::getDAO('GiftDAO');
		$giftSubscriptions = $giftDao->getGiftsByTypeAndRecipient(
			ASSOC_TYPE_JOURNAL,
			$journalId,
			GIFT_TYPE_SUBSCRIPTION,
			$userId
		);

		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);

		$templateMgr->assign(array(
			'journalTitle' => $journal->getLocalizedName(),
			'journalPath' => $journal->getPath(),
			'acceptGiftSubscriptionPayments' => $acceptGiftSubscriptionPayments,
			'giftSubscriptions' => $giftSubscriptions,
		));
		$templateMgr->display('user/gifts.tpl');

	}

	/**
	 * User redeems a gift
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function redeemGift($args, $request) {
		$this->validate();

		if (empty($args)) $request->redirect(null, 'dashboard');

		$journal = $request->getJournal();
		if (!$journal) $request->redirect(null, 'dashboard');

		// Ensure gift payments are enabled
		import('classes.payment.ojs.OJSPaymentManager');
		$paymentManager = new OJSPaymentManager($request);
		$acceptGiftPayments = $paymentManager->acceptGiftPayments();
		if (!$acceptGiftPayments) $request->redirect(null, 'dashboard');

		$journalId = $journal->getId();
		$user = $request->getUser();
		$userId = $user->getId();
		$giftId = isset($args[0]) ? (int) $args[0] : 0;

		// Try to redeem the gift
		$giftDao = DAORegistry::getDAO('GiftDAO');
		$status = $giftDao->redeemGift(
			ASSOC_TYPE_JOURNAL,
			$journalId,
			$userId,
			$giftId
		);

		// Report redeem status to user
		import('classes.notification.NotificationManager');
		$notificationManager = new NotificationManager();

		switch ($status) {
			case GIFT_REDEEM_STATUS_SUCCESS:
				$notificationType = NOTIFICATION_TYPE_GIFT_REDEEM_STATUS_SUCCESS;
				break;
			case GIFT_REDEEM_STATUS_ERROR_NO_GIFT_TO_REDEEM:
				$notificationType = NOTIFICATION_TYPE_GIFT_REDEEM_STATUS_ERROR_NO_GIFT_TO_REDEEM;
				break;
			case GIFT_REDEEM_STATUS_ERROR_GIFT_ALREADY_REDEEMED:
				$notificationType = NOTIFICATION_TYPE_GIFT_REDEEM_STATUS_ERROR_GIFT_ALREADY_REDEEMED;
				break;
			case GIFT_REDEEM_STATUS_ERROR_GIFT_INVALID:
				$notificationType = NOTIFICATION_TYPE_GIFT_REDEEM_STATUS_ERROR_GIFT_INVALID;
				break;
			case GIFT_REDEEM_STATUS_ERROR_SUBSCRIPTION_TYPE_INVALID:
				$notificationType = NOTIFICATION_TYPE_GIFT_REDEEM_STATUS_ERROR_SUBSCRIPTION_TYPE_INVALID;
				break;
			case GIFT_REDEEM_STATUS_ERROR_SUBSCRIPTION_NON_EXPIRING:
				$notificationType = NOTIFICATION_TYPE_GIFT_REDEEM_STATUS_ERROR_SUBSCRIPTION_NON_EXPIRING;
				break;
			default:
				$notificationType = NOTIFICATION_TYPE_NO_GIFT_TO_REDEEM;
		}

		$user = $request->getUser();

		$notificationManager->createTrivialNotification($user->getId(), $notificationType);
		$request->redirect(null, 'user', 'gifts');
	}

	/**
	 * Display subscriptions page
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function subscriptions($args, $request) {
		$this->validate();

		$journal = $request->getJournal();
		if (!$journal) $request->redirect(null, 'dashboard');
		if ($journal->getSetting('publishingMode') !=  PUBLISHING_MODE_SUBSCRIPTION) $request->redirect(null, 'dashboard');

		$journalId = $journal->getId();
		$subscriptionTypeDao = DAORegistry::getDAO('SubscriptionTypeDAO');
		$individualSubscriptionTypesExist = $subscriptionTypeDao->subscriptionTypesExistByInstitutional($journalId, false);
		$institutionalSubscriptionTypesExist = $subscriptionTypeDao->subscriptionTypesExistByInstitutional($journalId, true);
		if (!$individualSubscriptionTypesExist && !$institutionalSubscriptionTypesExist) $request->redirect(null, 'dashboard');

		$user = $request->getUser();
		$userId = $user->getId();
		$templateMgr = TemplateManager::getManager($request);

		// Subscriptions contact and additional information
		$subscriptionName = $journal->getSetting('subscriptionName');
		$subscriptionEmail = $journal->getSetting('subscriptionEmail');
		$subscriptionPhone = $journal->getSetting('subscriptionPhone');
		$subscriptionMailingAddress = $journal->getSetting('subscriptionMailingAddress');
		$subscriptionAdditionalInformation = $journal->getLocalizedSetting('subscriptionAdditionalInformation');
		// Get subscriptions and options for current journal
		if ($individualSubscriptionTypesExist) {
			$subscriptionDao = DAORegistry::getDAO('IndividualSubscriptionDAO');
			$userIndividualSubscription = $subscriptionDao->getSubscriptionByUserForJournal($userId, $journalId);
			$templateMgr->assign('userIndividualSubscription', $userIndividualSubscription);
		}

		if ($institutionalSubscriptionTypesExist) {
			$subscriptionDao = DAORegistry::getDAO('InstitutionalSubscriptionDAO');
			$userInstitutionalSubscriptions = $subscriptionDao->getSubscriptionsByUserForJournal($userId, $journalId);
			$templateMgr->assign('userInstitutionalSubscriptions', $userInstitutionalSubscriptions);
		}

		import('classes.payment.ojs.OJSPaymentManager');
		$paymentManager = new OJSPaymentManager($request);
		$acceptSubscriptionPayments = $paymentManager->acceptSubscriptionPayments();

		$this->setupTemplate($request);

		$templateMgr->assign(array(
			'subscriptionName' => $subscriptionName,
			'subscriptionEmail' => $subscriptionEmail,
			'subscriptionPhone' => $subscriptionPhone,
			'subscriptionMailingAddress' => $subscriptionMailingAddress,
			'subscriptionAdditionalInformation' => $subscriptionAdditionalInformation,
			'journalTitle' => $journal->getLocalizedName(),
			'journalPath' => $journal->getPath(),
			'acceptSubscriptionPayments' => $acceptSubscriptionPayments,
			'individualSubscriptionTypesExist' => $individualSubscriptionTypesExist,
			'institutionalSubscriptionTypesExist' => $institutionalSubscriptionTypesExist,
		));
		$templateMgr->display('user/subscriptions.tpl');

	}

	/**
	 * Determine if the journal's setup has been sufficiently completed.
	 * @param $journal Object
	 * @return boolean True iff setup is incomplete
	 */
	function _checkIncompleteSetup($journal) {
		if($journal->getLocalizedAcronym() == '' || $journal->getSetting('contactEmail') == '' ||
		   $journal->getSetting('contactName') == '' || $journal->getLocalizedSetting('abbreviation') == '') {
			return true;
		} else return false;
	}

	/**
	 * Become a given role.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function become($args, $request) {
		parent::validate(true);

		$journal = $request->getJournal();
		$user = $request->getUser();

		switch (array_shift($args)) {
			case 'author':
				$roleId = ROLE_ID_AUTHOR;
				$deniedKey = 'user.noRoles.submitArticleRegClosed';
				break;
			case 'reviewer':
				$roleId = ROLE_ID_REVIEWER;
				$deniedKey = 'user.noRoles.regReviewerClosed';
				break;
			default:
				return $request->redirect(null, null, 'index');
		}

		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userGroup = $userGroupDao->getDefaultByRoleId($journal->getId(), $roleId);
		if ($userGroup->getPermitSelfRegistration()) {
			$userGroupDao->assignUserToGroup($user->getId(), $userGroup->getId());
			$request->redirectUrl($request->getUserVar('source'));
		} else {
			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign('message', $deniedKey);
			return $templateMgr->display('frontend/pages/message.tpl');
		}
	}

	/**
	 * Validate that user is logged in.
	 * Redirects to login form if not logged in.
	 * @param $loginCheck boolean check if user is logged in
	 */
	function validate($loginCheck = true) {
		parent::validate();
		if ($loginCheck && !Validation::isLoggedIn()) {
			Validation::redirectLogin();
		}
	}

	/**
	 * Setup common template variables.
	 * @param $request PKPRequest
	 */
	function setupTemplate($request = null) {
		parent::setupTemplate($request);
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_AUTHOR, LOCALE_COMPONENT_APP_EDITOR, LOCALE_COMPONENT_APP_MANAGER, LOCALE_COMPONENT_PKP_GRID);
	}


	//
	// Payments
	//
	/**
	 * Purchase a subscription.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function purchaseSubscription($args, $request) {
		$this->validate();

		if (empty($args)) $request->redirect(null, 'dashboard');

		$journal = $request->getJournal();
		if (!$journal) $request->redirect(null, 'dashboard');
		if ($journal->getSetting('publishingMode') != PUBLISHING_MODE_SUBSCRIPTION) $request->redirect(null, 'dashboard');

		import('classes.payment.ojs.OJSPaymentManager');
		$paymentManager = new OJSPaymentManager($request);
		$acceptSubscriptionPayments = $paymentManager->acceptSubscriptionPayments();
		if (!$acceptSubscriptionPayments) $request->redirect(null, 'dashboard');

		$this->setupTemplate($request);
		$user = $request->getUser();
		$userId = $user->getId();
		$journalId = $journal->getId();

		$institutional = array_shift($args);
		if (!empty($args)) {
			$subscriptionId = (int) array_shift($args);
		}

		if ($institutional == 'institutional') {
			$institutional = true;
			import('classes.subscription.form.UserInstitutionalSubscriptionForm');
			$subscriptionDao = DAORegistry::getDAO('InstitutionalSubscriptionDAO');
		} else {
			$institutional = false;
			import('classes.subscription.form.UserIndividualSubscriptionForm');
			$subscriptionDao = DAORegistry::getDAO('IndividualSubscriptionDAO');
		}

		if (isset($subscriptionId)) {
			// Ensure subscription to be updated is for this user
			if (!$subscriptionDao->subscriptionExistsByUser($subscriptionId, $userId)) {
				$request->redirect(null, 'dashboard');
			}

			// Ensure subscription can be updated
			$subscription = $subscriptionDao->getSubscription($subscriptionId);
			$subscriptionStatus = $subscription->getStatus();
			import('classes.subscription.Subscription');
			$validStatus = array(
				SUBSCRIPTION_STATUS_ACTIVE,
				SUBSCRIPTION_STATUS_AWAITING_ONLINE_PAYMENT,
				SUBSCRIPTION_STATUS_AWAITING_MANUAL_PAYMENT
			);

			if (!in_array($subscriptionStatus, $validStatus)) $request->redirect(null, 'dashboard');

			if ($institutional) {
				$subscriptionForm = new UserInstitutionalSubscriptionForm($request, $userId, $subscriptionId);
			} else {
				$subscriptionForm = new UserIndividualSubscriptionForm($request, $userId, $subscriptionId);
			}

		} else {
			if ($institutional) {
				$subscriptionForm = new UserInstitutionalSubscriptionForm($request, $userId);
			} else {
				// Ensure user does not already have an individual subscription
				if ($subscriptionDao->subscriptionExistsByUserForJournal($userId, $journalId)) {
					$request->redirect(null, 'dashboard');
				}
				$subscriptionForm = new UserIndividualSubscriptionForm($request, $userId);
			}
		}

		$subscriptionForm->initData();
		$subscriptionForm->display();
	}

	/**
	 * Pay for a subscription purchase.
 	 * @param $args array
	 * @param $request PKPRequest
	 */
	function payPurchaseSubscription($args, $request) {
		$this->validate();

		if (empty($args)) $request->redirect(null, 'dashboard');

		$journal = $request->getJournal();
		if (!$journal) $request->redirect(null, 'dashboard');
		if ($journal->getSetting('publishingMode') != PUBLISHING_MODE_SUBSCRIPTION) $request->redirect(null, 'dashboard');

		import('classes.payment.ojs.OJSPaymentManager');
		$paymentManager = new OJSPaymentManager($request);
		$acceptSubscriptionPayments = $paymentManager->acceptSubscriptionPayments();
		if (!$acceptSubscriptionPayments) $request->redirect(null, 'dashboard');

		$this->setupTemplate($request);
		$user = $request->getUser();
		$userId = $user->getId();
		$journalId = $journal->getId();

		$institutional = array_shift($args);
		if (!empty($args)) {
			$subscriptionId = (int) array_shift($args);
		}

		if ($institutional == 'institutional') {
			$institutional = true;
			import('classes.subscription.form.UserInstitutionalSubscriptionForm');
			$subscriptionDao = DAORegistry::getDAO('InstitutionalSubscriptionDAO');
		} else {
			$institutional = false;
			import('classes.subscription.form.UserIndividualSubscriptionForm');
			$subscriptionDao = DAORegistry::getDAO('IndividualSubscriptionDAO');
		}

		if (isset($subscriptionId)) {
			// Ensure subscription to be updated is for this user
			if (!$subscriptionDao->subscriptionExistsByUser($subscriptionId, $userId)) {
				$request->redirect(null, 'dashboard');
			}

			// Ensure subscription can be updated
			$subscription = $subscriptionDao->getSubscription($subscriptionId);
			$subscriptionStatus = $subscription->getStatus();
			import('classes.subscription.Subscription');
			$validStatus = array(
				SUBSCRIPTION_STATUS_ACTIVE,
				SUBSCRIPTION_STATUS_AWAITING_ONLINE_PAYMENT,
				SUBSCRIPTION_STATUS_AWAITING_MANUAL_PAYMENT
			);

			if (!in_array($subscriptionStatus, $validStatus)) $request->redirect(null, 'dashboard');

			if ($institutional) {
				$subscriptionForm = new UserInstitutionalSubscriptionForm($request, $userId, $subscriptionId);
			} else {
				$subscriptionForm = new UserIndividualSubscriptionForm($request, $userId, $subscriptionId);
			}

		} else {
			if ($institutional) {
				$subscriptionForm = new UserInstitutionalSubscriptionForm($request, $userId);
			} else {
				// Ensure user does not already have an individual subscription
				if ($subscriptionDao->subscriptionExistsByUserForJournal($userId, $journalId)) {
					$request->redirect(null, 'dashboard');
				}
				$subscriptionForm = new UserIndividualSubscriptionForm($request, $userId);
			}
		}

		$subscriptionForm->readInputData();

		// Check for any special cases before trying to save
		if ($request->getUserVar('addIpRange')) {
			$editData = true;
			$ipRanges = $subscriptionForm->getData('ipRanges');
			$ipRanges[] = '';
			$subscriptionForm->setData('ipRanges', $ipRanges);

		} else if (($delIpRange = $request->getUserVar('delIpRange')) && count($delIpRange) == 1) {
			$editData = true;
			list($delIpRange) = array_keys($delIpRange);
			$delIpRange = (int) $delIpRange;
			$ipRanges = $subscriptionForm->getData('ipRanges');
			array_splice($ipRanges, $delIpRange, 1);
			$subscriptionForm->setData('ipRanges', $ipRanges);
		}

		if (isset($editData)) {
			$subscriptionForm->display();
		} else {
			if ($subscriptionForm->validate()) {
				$subscriptionForm->execute();
			} else {
				$subscriptionForm->display();
			}
		}
	}

	/**
	 * Complete the purchase subscription process.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function completePurchaseSubscription($args, $request) {
		$this->validate();

		if (count($args) != 2) $request->redirect(null, 'dashboard');

		$journal = $request->getJournal();
		if (!$journal) $request->redirect(null, 'dashboard');
		if ($journal->getSetting('publishingMode') != PUBLISHING_MODE_SUBSCRIPTION) $request->redirect(null, 'dashboard');

		import('classes.payment.ojs.OJSPaymentManager');
		$paymentManager = new OJSPaymentManager($request);
		$acceptSubscriptionPayments = $paymentManager->acceptSubscriptionPayments();
		if (!$acceptSubscriptionPayments) $request->redirect(null, 'dashboard');

		$this->setupTemplate($request);
		$user = $request->getUser();
		$userId = $user->getId();
		$institutional = array_shift($args);
		$subscriptionId = (int) array_shift($args);

		if ($institutional == 'institutional') {
			$subscriptionDao = DAORegistry::getDAO('InstitutionalSubscriptionDAO');
		} else {
			$subscriptionDao = DAORegistry::getDAO('IndividualSubscriptionDAO');
		}

		if (!$subscriptionDao->subscriptionExistsByUser($subscriptionId, $userId)) $request->redirect(null, 'dashboard');

		$subscription = $subscriptionDao->getSubscription($subscriptionId);
		$subscriptionStatus = $subscription->getStatus();
		import('classes.subscription.Subscription');
		$validStatus = array(SUBSCRIPTION_STATUS_ACTIVE, SUBSCRIPTION_STATUS_AWAITING_ONLINE_PAYMENT);

		if (!in_array($subscriptionStatus, $validStatus)) $request->redirect(null, 'dashboard');

		$subscriptionTypeDao = DAORegistry::getDAO('SubscriptionTypeDAO');
		$subscriptionType = $subscriptionTypeDao->getSubscriptionType($subscription->getTypeId());

		$queuedPayment = $paymentManager->createQueuedPayment($journal->getId(), PAYMENT_TYPE_PURCHASE_SUBSCRIPTION, $user->getId(), $subscriptionId, $subscriptionType->getCost(), $subscriptionType->getCurrencyCodeAlpha());
		$queuedPaymentId = $paymentManager->queuePayment($queuedPayment);

		$paymentManager->displayPaymentForm($queuedPaymentId, $queuedPayment);
	}

	/**
	 * Pay the "renew subscription" fee.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function payRenewSubscription($args, $request) {
		$this->validate();

		if (count($args) != 2) $request->redirect(null, 'dashboard');

		$journal = $request->getJournal();
		if (!$journal) $request->redirect(null, 'dashboard');
		if ($journal->getSetting('publishingMode') != PUBLISHING_MODE_SUBSCRIPTION) $request->redirect(null, 'dashboard');

		import('classes.payment.ojs.OJSPaymentManager');
		$paymentManager = new OJSPaymentManager($request);
		$acceptSubscriptionPayments = $paymentManager->acceptSubscriptionPayments();
		if (!$acceptSubscriptionPayments) $request->redirect(null, 'dashboard');

		$this->setupTemplate($request);
		$user = $request->getUser();
		$userId = $user->getId();
		$institutional = array_shift($args);
		$subscriptionId = (int) array_shift($args);

		if ($institutional == 'institutional') {
			$subscriptionDao = DAORegistry::getDAO('InstitutionalSubscriptionDAO');
		} else {
			$subscriptionDao = DAORegistry::getDAO('IndividualSubscriptionDAO');
		}

		if (!$subscriptionDao->subscriptionExistsByUser($subscriptionId, $userId)) $request->redirect(null, 'dashboard');

		$subscription = $subscriptionDao->getSubscription($subscriptionId);

		if ($subscription->isNonExpiring()) $request->redirect(null, 'dashboard');

		import('classes.subscription.Subscription');
		$subscriptionStatus = $subscription->getStatus();
		$validStatus = array(
			SUBSCRIPTION_STATUS_ACTIVE,
			SUBSCRIPTION_STATUS_AWAITING_ONLINE_PAYMENT,
			SUBSCRIPTION_STATUS_AWAITING_MANUAL_PAYMENT
		);

		if (!in_array($subscriptionStatus, $validStatus)) $request->redirect(null, 'dashboard');

		$subscriptionTypeDao = DAORegistry::getDAO('SubscriptionTypeDAO');
		$subscriptionType = $subscriptionTypeDao->getSubscriptionType($subscription->getTypeId());

		$queuedPayment = $paymentManager->createQueuedPayment($journal->getId(), PAYMENT_TYPE_RENEW_SUBSCRIPTION, $user->getId(), $subscriptionId, $subscriptionType->getCost(), $subscriptionType->getCurrencyCodeAlpha());
		$queuedPaymentId = $paymentManager->queuePayment($queuedPayment);

		$paymentManager->displayPaymentForm($queuedPaymentId, $queuedPayment);
	}

	/**
	 * Pay for a membership.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function payMembership($args, $request) {
		$this->validate();
		$this->setupTemplate($request);

		import('classes.payment.ojs.OJSPaymentManager');
		$paymentManager = new OJSPaymentManager($request);

		$journal = $request->getJournal();
		$user = $request->getUser();

		$queuedPayment = $paymentManager->createQueuedPayment($journal->getId(), PAYMENT_TYPE_MEMBERSHIP, $user->getId(), null,  $journal->getSetting('membershipFee'));
		$queuedPaymentId = $paymentManager->queuePayment($queuedPayment);

		$paymentManager->displayPaymentForm($queuedPaymentId, $queuedPayment);
	}
}

?>
