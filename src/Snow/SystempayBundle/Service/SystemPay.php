<?php

namespace Snow\SystempayBundle\Service;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;


/**
 * Class SystemPay
 * @package Snow\SystempayBundle\Service
 */
class SystemPay
{
    /**
     * @var string
     */
    private $paymentUrl = 'https://systempay.cyberpluspaiement.com/vads-payment/';

    /**
     * @var array
     */
    private $mandatoryFields = array(
        'action_mode' => null,
        'ctx_mode' => null,
        'page_action' => null,
        'payment_config' => null,
        'site_id' => null,
        'version' => null,
        'redirect_success_message' => null,
        'redirect_error_message' => null,
        'url_return' => null,
    );

    /**
     * @var string
     */
    private $key;

    protected $logger;

    public function __construct(Container $container)
    {
        $this->logger = $container->get("snow.systempay.logger");

        foreach ($this->mandatoryFields as $field => $value) :
            $this->mandatoryFields[$field] = $container->getParameter(sprintf('snow_systempay.%s', $field));
        endforeach;

        if ($this->mandatoryFields['ctx_mode'] == "TEST") :
            $this->key = $container->getParameter('snow_systempay.key_dev');
        else :
            $this->key = $container->getParameter('snow_systempay.key_prod');
        endif;

    }

    /**
     * @param int $id_transaction
     * @param int $amount
     * Use int :
     * 10,28 € = 1028
     * 95 € = 9500
     * @param int $currency
     * Euro => 978
     * US Dollar => 840
     * @return $this
     */
    public function init($id_transaction, $amount, $currency = 978)
    {
        $this->mandatoryFields['amount'] = $amount;
        $this->mandatoryFields['currency'] = $currency;
        $this->mandatoryFields['trans_id'] = sprintf('%06d', $id_transaction); // need 6 digit number (empty space with 0)
        $this->mandatoryFields['trans_date'] = gmdate('YmdHis');
        return $this;
    }

    /**
     * @param $fields
     * remove "vads_" prefix and form an array that will looks like :
     * trans_id => x
     * @return $this
     */
    public function setOptionnalFields($fields)
    {
        foreach ($fields as $field => $value)
            if (empty($this->mandatoryFields[$field]))
                $this->mandatoryFields[$field] = $value;
        return $this;
    }

    /**
     * @return array
     */
    public function getResponse()
    {
        $this->mandatoryFields['signature'] = $this->getSignature();
        return $this->mandatoryFields;
    }

    /**
     * Check the verification from the bank server
     * @param Request $request
     * @return Array
     */
    public function responseHandler(Request $request)
    {
        $query = $request->request->all();

        $retour['statut'] = "???";
        $retour['id_trans'] = $query['vads_trans_id'];
        $retour['amount'] = $query['vads_amount'];

        // Check signature
        if (!empty($query['signature']))
        {
            $signature = $query['signature'];
            unset ($query['signature']);
            if ($signature == $this->getSignature($query))
            {
                $this->writeLog( json_encode($query) );

                if ($query['vads_trans_status'] == "AUTHORISED") :
                    $retour['statut'] = "ok";
                else :
                    $retour['statut'] = $query['vads_trans_status'];
                endif;
            }else{
                $this->writeErrorLog( "Fail check signature with id_trans : [".$retour['id_trans']."] ".json_encode($query) );
            }
        }else{
            $this->writeErrorLog( "Empty signature with id_trans : [".$retour['id_trans']."] ".json_encode($query) );
        }
        return $retour;
    }

    /**
     * @return string
     */
    public function getPaymentUrl()
    {
        return $this->paymentUrl;
    }


    /**
     * @param array $fields
     * @return array
     */
    private function setPrefixToFields(array $fields)
    {
        $newTab = array();
        foreach ($fields as $field => $value)
            $newTab[sprintf('vads_%s', $field)] = $value;
        return $newTab;
    }

    /**
     * @param null $fields
     * @return string
     */
    private function getSignature($fields = null)
    {
        if (!$fields)
            $fields = $this->mandatoryFields = $this->setPrefixToFields($this->mandatoryFields);
        ksort($fields);
        $contenu_signature = "";
        foreach ($fields as $field => $value)
            $contenu_signature .= $value."+";
            $contenu_signature .= $this->key;
            $signature = sha1($contenu_signature);
        return $signature;
    }

    /**
     * Write INFO element in a specifig log to Systempay
     * @param $string
     */
    public function writeLog($string)
    {
        $this->logger->info($string);
    }

    /**
     * Write ERROR in the log to systempay
     * @param $string
     */
    public function writeErrorLog($string)
    {
        $this->logger->error($string);
    }
}
