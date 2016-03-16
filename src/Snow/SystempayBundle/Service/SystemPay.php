<?php

namespace Snow\SystempayBundle\Service;

use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Snow\SystempayBundle\Entity\Transaction;

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


    public function __construct(Container $container)
    {

        foreach ($this->mandatoryFields as $field => $value)
            $this->mandatoryFields[$field] = $container->getParameter(sprintf('snow_systempay.%s', $field));
        if ($this->mandatoryFields['ctx_mode'] == "TEST")
            $this->key = $container->getParameter('snow_systempay.key_dev');
        else
            $this->key = $container->getParameter('snow_systempay.key_prod');

    }

    /**
     * @param int $id_transaction
     * @param int $currency
     * Euro => 978
     * US Dollar => 840
     * @param int $amount
     * Use int :
     * 10,28 € = 1028
     * 95 € = 9500
     * @return $this
     */
    public function init($id_transaction, $currency = 978, $amount = 1000)
    {
        $this->mandatoryFields['amount'] = $amount;
        $this->mandatoryFields['currency'] = $currency;
        $this->mandatoryFields['trans_id'] = $id_transaction;
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
     * @param Request $request
     * @return bool
     */
    public function responseHandler(Request $request)
    {
        $query = $request->request->all();

        // Check signature
        if (!empty($query['signature']))
        {
            $signature = $query['signature'];
            unset ($query['signature']);
            if ($signature == $this->getSignature($query))
            {
                $transaction = new Transaction(); // $query['vads_trans_id']
                $transaction->setStatus($query['vads_trans_status']);
                if ($query['vads_trans_status'] == "AUTHORISED")
                $transaction->setPaid(true);
                $transaction->setUpdatedAt(new \DateTime());
                $transaction->setLogResponse(json_encode($query));

                return $transaction;
            }
        }
        return null;
    }

    /**
     * @return string
     */
    public function getPaymentUrl()
    {
        return $this->paymentUrl;
    }

    /**
     * @return Transaction
     */
    public function getTransaction()
    {
        return $this->transaction;
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
}
