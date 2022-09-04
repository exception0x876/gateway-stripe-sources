<?php
/**
 * Stripe Sources Gateway.
 *
 * The Stripe Sources documentation can be found at:
 * https://stripe.com/docs/sources
 *
 * @package blesta
 * @subpackage blesta.components.gateways.StripeSources
 * @copyright Copyright (c) 2020, Kieran Coldron.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class StripeSources extends NonmerchantGateway
{
    /**
     * @var string The base URL of API requests
     */
    private $base_url = 'https://api.stripe.com/v1/';

    /**
     * @var array An array of meta data for this gateway
     */
    private $meta;

    /**
     * Construct a new merchant gateway.
     */
    public function __construct()
    {
        Loader::loadComponents($this, ['Input']);

        // Load components required by this gateway
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        // Load the language required by this gateway
        Language::loadLang('stripe_sources', null, dirname(__FILE__) . DS . 'language' . DS);

    }

    /**
     * {@inheritdoc}
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * {@inheritdoc}
     */
    public function getSettings(array $meta = null)
    {
        $this->view = $this->makeView('settings', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('meta', $meta);

        return $this->view->fetch();
    }


    /**
     * {@inheritdoc}
     */
    public function editSettings(array $meta)
    {
        // Validate the given meta data to ensure it meets the requirements
        $rules = [
            'publishable_key' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('StripeSources.!error.publishable_key.empty', true)
                ]
            ],
            'secret_key' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('StripeSources.!error.secret_key.empty', true)
                ],
                'valid' => [
                    'rule' => [[$this, 'validateConnection']],
                    'message' => Language::_('StripeSources.!error.secret_key.valid', true)
                ]
            ],
            'signing_key' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('StripeSources.!error.signing_key.empty', true)
                ],
            ]
        ];

        $this->Input->setRules($rules);
        return $meta;
    }

    /**
     * {@inheritdoc}
     */
    public function encryptableFields()
    {
        return ['secret_key'];
    }

    /**
     * {@inheritdoc}
     */
    public function setMeta(array $meta = null)
    {
        $this->meta = $meta;
    }


     /**
     * {@inheritdoc}
     */
    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null)
    {
        $this->view = $this->makeView("process", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));

        // Load the models required
        Loader::loadModels($this, ['Companies', 'Clients']);        

        
        // get company information
        $company = $this->Companies->get(Configure::get('Blesta.company_id'));
        $client = $this->Clients->get($contact_info['client_id']);

        if (isset($invoice_amounts) && is_array($invoice_amounts)) {
            $invoices = $this->serializeInvoices($invoice_amounts);
        }

        $transaction_id = md5($contact_info['client_id'] . '@' . (!empty($invoices) ? $invoices : time()));


        $fields = [
            "currency" =>  $this->ifSet($this->currency),
            "amount" => $this->formatAmount($amount, $this->currency),
            "owner" => [
                'name' => (!empty($contact_info['first_name']) && !empty($contact_info['last_name'])
                    ? $this->ifSet($contact_info['first_name']) . ' ' . $this->ifSet($contact_info['last_name'])
                    : ''),
                "email" => $this->ifSet($client->email)
            ],
            'metadata' => [
                'transaction_id' => $this->ifSet($transaction_id),
                'invoices' => $this->ifSet($invoices),
                'client_id' => $this->ifSet($contact_info['client_id'])
            ],
            'return_url' => $this->ifSet($options['return_url']),
            'stripe_key' => $this->ifSet($this->meta['publishable_key'])
        ];


        Loader::loadHelpers($this, array("Form", "Html"));
        $this->view->set("fields", $fields);
        return $this->view->fetch();

    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $get, array $post)
    {
        
        $this->loadApi();

        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $event = null;
                
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $this->meta['signing_key']
            );
        } catch(\UnexpectedValueException $e) {
            // Invalid payload
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
            exit();
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
            exit();
        }

        $this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($event), 'input', true);    


        $status = 'error';
        $return_status = false;

        // Handle the event
        switch ($event->type) {
            case 'source.chargeable':
                $charge = \Stripe\Charge::create([
                    'amount' => $event->data->object->amount,
                    'currency' => $event->data->object->currency,
                    'source' => $event->data->object->id,
                    'metadata' => [
                        'transaction_id' => $this->ifSet($event->data->object->metadata->transaction_id),
                        'invoices' => $this->ifSet($event->data->object->metadata->invoices),
                        'client_id' => $this->ifSet($event->data->object->metadata->client_id)
                    ]
                ]);

                $this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($charge), 'output', true);    
                
                $status = 'pending';
                $return_status = true;
            break;
            case 'charge.succeeded':
                    $status = 'approved';
                    $return_status = true;
                break;
                case 'charge.canceled':
                case 'source.canceled':
                    $status = 'canceled';
                    $return_status = true;
                    break;
                case 'charge.pending':
                    $status = 'pending';
                    $return_status = true;

                default:
                    break;
        }

        return [
            'client_id' => $this->ifSet($event->data->object->metadata->client_id),
            'amount' => $this->unformatAmount($event->data->object->amount, $event->data->object->currency),
            'currency' => $this->ifSet($event->data->object->currency),
            'status' => $status,
            'reference_id' => null,
            'transaction_id' => $this->ifSet($event->data->object->metadata->transaction_id),
            'invoices' => $this->unserializeInvoices($event->data->object->metadata->invoices)
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function success(array $get, array $post)
    {
        $this->loadApi();

        $source = \Stripe\Source::retrieve($get["source"],   
                    ['client_secret' => $get["client_secret"]]);

       

        return [
            'client_id' => $this->ifSet($get["client_id"]),
            'amount' => $this->unformatAmount($source->amount, $source->currency),
            'currency' => $this->ifSet($source->currency),
            'status' => "approved",
            'reference_id' => null,
            'transaction_id' => $this->ifSet($source->metadata->transaction_id),
            'invoices' => $this->unserializeInvoices($source->metadata->invoices)
        ];
    }

     /**
     * {@inheritdoc}
     */
    public function capture($reference_id, $transaction_id, $amount, array $invoice_amounts = null)
    {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

     /**
     * {@inheritdoc}
     */
    public function void($reference_id, $transaction_id, $notes = null)
    {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

     /**
     * {@inheritdoc}
     */
    public function refund($reference_id, $transaction_id, $amount, $notes = null)
    {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }


    /**
     * Serializes an array of invoice info into a string.
     *
     * @param array A numerically indexed array invoices info including:
     *  - id The ID of the invoice
     *  - amount The amount relating to the invoice
     * @return string A serialized string of invoice info in the format of key1=value1|key2=value2
     */
     private function serializeInvoices(array $invoices)
     {
         $str = '';
         foreach ($invoices as $i => $invoice) {
             $str .= ($i > 0 ? '|' : '') . $invoice['id'] . '=' . $invoice['amount'];
         }
 
         return $str;
     }
 
     /**
      * Unserializes a string of invoice info into an array.
      *
      * @param string A serialized string of invoice info in the format of key1=value1|key2=value2
      * @param mixed $str
      * @return array A numerically indexed array invoices info including:
      *  - id The ID of the invoice
      *  - amount The amount relating to the invoice
      */
     private function unserializeInvoices($str)
     {
         $invoices = [];
         $temp = explode('|', $str);
         foreach ($temp as $pair) {
             $pairs = explode('=', $pair, 2);
             if (count($pairs) != 2) {
                 continue;
             }
             $invoices[] = ['id' => $pairs[0], 'amount' => $pairs[1]];
         }
 
         return $invoices;
     }


    /**
     * Convert amount from decimal value to integer representation of cents
     *
     * @param float $amount
     * @param string $currency
     * @return int The amount in cents
     */
     private function formatAmount($amount, $currency)
     {
         $non_decimal_currencies = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY',
             'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VUV', 'XAF', 'XOF', 'XPF'];
 
         if (is_numeric($amount) && !in_array($currency, $non_decimal_currencies)) {
             $amount *= 100;
         }
         return (int)round($amount);
     }


    /**
     * Convert amount from integer to decimal
     *
     * @param float $amount
     * @param string $currency
     * @return int The amount in cents
     */
     private function unformatAmount($amount, $currency)
     {
         $non_decimal_currencies = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY',
             'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VUV', 'XAF', 'XOF', 'XPF'];
 
         if (is_numeric($amount) && !in_array($currency, $non_decimal_currencies)) {
             $amount /= 100;
         }
         return (int)round($amount);
     }


     /**
     * Loads the API if not already loaded
     */
    private function loadApi()
    {
        Loader::load(dirname(__FILE__) . DS . 'vendor' . DS . 'stripe' . DS . 'stripe-php' . DS . 'init.php');
        Stripe\Stripe::setApiKey($this->ifSet($this->meta['secret_key']));

        // Include identifying information about this being a gateway for Blesta
        Stripe\Stripe::setAppInfo('Blesta ' . $this->getName(), $this->getVersion(), 'https://blesta.com');
    }
}