<?php
// Errors
$lang['StripeSources.!error.auth'] = 'The gateway could not authenticate.';
$lang['StripeSources.!error.publishable_key.empty'] = 'Please enter a Publishable Key.';
$lang['StripeSources.!error.secret_key.empty'] = 'Please enter a Secret Key.';
$lang['StripeSources.!error.secret_key.valid'] = 'Unable to connect to the Stripe API using the given Secret Key.';
$lang['StripeSources.!error.signing_key.empty'] = 'Please enter a Signing Key.';

$lang['StripeSources.name'] = 'Stripe (Alipay)';
$lang['StripeSources.description'] = 'Blesta payment gateway for Credit Card and Alipay payments via Stripe Sources';

// Settings
$lang['StripeSources.publishable_key'] = 'API Publishable Key';
$lang['StripeSources.secret_key'] = 'API Secret Key';
$lang['StripeSources.signing_key'] = 'Webhooks Signing Key';
$lang['StripeSources.tooltip_publishable_key'] = 'Your API Publishable Key is specific to either live or test mode. Be sure you are using the correct key.';
$lang['StripeSources.tooltip_secret_key'] = 'Your API Secret Key is specific to either live or test mode. Be sure you are using the correct key.';
$lang['StripeSources.tooltip_signing_key'] = 'Your Webhook Signing Key is specific to either live or test mode. Be sure you are using the correct key.';


// Charge description
$lang['StripeSources.charge_description_default'] = 'Charge for specified amount';
$lang['StripeSources.charge_description'] = 'Charge for %1$s'; // Where %1$s is a comma seperated list of invoice ID display codes
