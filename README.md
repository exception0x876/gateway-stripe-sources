# Stripe WeChat/Alipay Gateway
This is a nonmerchant gateway for Blesta that integrates with [Alipay](https://global.alipay.com/) and WeChat Pay via stripe.

## Install the Gateway

1. Upload the source code to a /components/gateways/nonmerchant/stripe_sources/ directory within
your Blesta installation path.

    For example:

    ```
    /var/www/html/blesta/components/nonmerchant/stripe_sources/
    ```

3. Log in to your admin Blesta account and navigate to
> Settings > Payment Gateways

4. Find the WeChat/Alipay Via Stripe gateway and click the "Install" button to install it

5. Click Manage and populate the required fields and setup your webhook.

6. You're done!


### Blesta Compatibility

|Blesta Version|Module Version|
|--------------|--------------|
|>= v4.9.0|v1.0.0|
