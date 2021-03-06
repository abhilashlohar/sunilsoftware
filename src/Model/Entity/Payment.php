<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Payment Entity
 *
 * @property int $id
 * @property int $voucher_no
 * @property int $company_id
 * @property \Cake\I18n\FrozenDate $transaction_date
 * @property string $narration
 *
 * @property \App\Model\Entity\Company $company
 * @property \App\Model\Entity\PaymentRow[] $payment_rows
 */
class Payment extends Entity
{

    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array
     */
    protected $_accessible = [
        '*' => true,
        'id' => false
    ];
	
	protected $_virtual = [
        'transaction_date'
    ];
                
                protected function _getTransactionDate()
    {
                                if(!empty($this->_properties['transaction_date']))
                                {
                                return date('Y-m-d', strtotime($this->_properties['transaction_date']));
                                }
                                else
                                { return "000:00:00";}
    }

}
