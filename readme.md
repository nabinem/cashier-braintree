# Laravel Cashier - Braintree Edition

[![Build Status](https://travis-ci.org/laravel/cashier-braintree.svg)](https://travis-ci.org/laravel/cashier-braintree)
[![Total Downloads](https://poser.pugx.org/laravel/cashier-braintree/d/total.svg)](https://packagist.org/packages/laravel/cashier-braintree)
[![Latest Stable Version](https://poser.pugx.org/laravel/cashier-braintree/v/stable.svg)](https://packagist.org/packages/laravel/cashier-braintree)
[![Latest Unstable Version](https://poser.pugx.org/laravel/cashier-braintree/v/unstable.svg)](https://packagist.org/packages/laravel/cashier-braintree)
[![License](https://poser.pugx.org/laravel/cashier-braintree/license.svg)](https://packagist.org/packages/laravel/cashier-braintree)

## Introduction

Laravel Cashier provides an expressive, fluent interface to [Braintree's](https://www.braintreepayments.com/) subscription billing services. It handles almost all of the boilerplate subscription billing code you are dreading writing. In addition to basic subscription management, Cashier can handle coupons, swapping subscription, cancellation grace periods, and even generate invoice PDFs.

## Support
   Laravel ^7.0|^8.0, PHP ^7.2|^8.0 <br>
   uses braintree/braintree_php: "~6.0" <br>
   Original source code: https://github.com/laravel/cashier-braintree 
   
## Installation
     Add repo url to repositories key in composer.json file.

    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/nabinem/cashier-braintree"
        }
    ]

    composer require nabinem/cashier-braintree:dev-braintree_6
    
## Uses
  On config/services.php file add: <br>
  
         'braintree' => [
          'model'  => App\User::class,
          'environment' => env('BRAINTREE_ENV'),
          'merchant_id' => env('BRAINTREE_MERCHANT_ID'),
          'public_key' => env('BRAINTREE_PUBLIC_KEY'),
          'private_key' => env('BRAINTREE_PRIVATE_KEY'),
      ],
  
  On boot method of AppServiceProvider.php add<br>
  
       use Braintree\Configuration as Braintree_Configuration;
       
        Braintree_Configuration::environment(config('services.braintree.environment'));
        Braintree_Configuration::merchantId(config('services.braintree.merchant_id'));
        Braintree_Configuration::publicKey(config('services.braintree.public_key'));
        Braintree_Configuration::privateKey(config('services.braintree.private_key'));
    
   ### Generating token
     use Braintree\ClientToken as Braintree_ClientToken;
     
     $clientToken = Braintree_ClientToken::generate();
  
  ### NOTE
  It seems Class alias has been removed in this new braintree SDK so if you have older code using
  namespace with name starting Braintree_... then change it to alias version.
         
            For eg;
            change 'use Braintree_Subscription' to 'Braintree\Subscription as Braintree_Subscription'
            'use Braintree\ClientToken' => 'use Braintree\ClientToken as Braintree_ClientToken'
            use Braintree\Plan as Braintree_Plan;
            use Braintree\AddOn as Braintree_AddOn;
            
            

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
