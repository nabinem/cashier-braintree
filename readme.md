# Laravel Cashier - Braintree Edition (Beta)

[![Build Status](https://travis-ci.org/laravel/cashier-braintree.svg)](https://travis-ci.org/laravel/cashier-braintree)
[![Total Downloads](https://poser.pugx.org/laravel/cashier-braintree/d/total.svg)](https://packagist.org/packages/laravel/cashier-braintree)
[![Latest Stable Version](https://poser.pugx.org/laravel/cashier-braintree/v/stable.svg)](https://packagist.org/packages/laravel/cashier-braintree)
[![Latest Unstable Version](https://poser.pugx.org/laravel/cashier-braintree/v/unstable.svg)](https://packagist.org/packages/laravel/cashier-braintree)
[![License](https://poser.pugx.org/laravel/cashier-braintree/license.svg)](https://packagist.org/packages/laravel/cashier-braintree)

## Introduction

Laravel Cashier provides an expressive, fluent interface to [Braintree's](https://www.braintreepayments.com/) subscription billing services. It handles almost all of the boilerplate subscription billing code you are dreading writing. In addition to basic subscription management, Cashier can handle coupons, swapping subscription, cancellation grace periods, and even generate invoice PDFs.

## Test Setup
You will need to set the following details locally and on your Braintree account in order to test. It's recommended that you sign-up for a [Braintree sandbox account](https://www.braintreepayments.com/sandbox).

### Local
#### .env
    BRAINTREE_MERCHANT_ID=
    BRAINTREE_PUBLIC_KEY=
    BRAINTREE_PRIVATE_KEY=
    BRAINTREE_MODEL=User

### Braintree
#### Plans
    * Plan ID: monthly-10-1, Price: $10, Billing cycle of every month
    * Plan ID: monthly-10-2, Price: $10, Billing cycle of every month
    * Plan ID: yearly-100-1, Price: $100, Billing cycle of every 12 months
#### Discount
    * Discount ID: coupon-1, Price: $5
    * Discount ID: plan-credit, Price $1


## Official Documentation

Documentation for Cashier can be found on the [Laravel website](http://laravel.com/docs/billing).

## License

Laravel Cashier is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
