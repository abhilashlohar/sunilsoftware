<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Event\Event;
use ArrayObject;

/**
 * ContraVoucherRows Model
 *
 * @property \App\Model\Table\JournalVouchersTable|\Cake\ORM\Association\BelongsTo $JournalVouchers
 * @property \App\Model\Table\LedgersTable|\Cake\ORM\Association\BelongsTo $Ledgers
 * @property \App\Model\Table\ReferenceDetailsTable|\Cake\ORM\Association\HasMany $ReferenceDetails
 *
 * @method \App\Model\Entity\ContraVoucherRow get($primaryKey, $options = [])
 * @method \App\Model\Entity\ContraVoucherRow newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\ContraVoucherRow[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\ContraVoucherRow|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\ContraVoucherRow patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\ContraVoucherRow[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\ContraVoucherRow findOrCreate($search, callable $callback = null, $options = [])
 */
class ContraVoucherRowsTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->setTable('contra_voucher_rows');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('ContraVouchers', [
            'foreignKey' => 'contra_voucher_id',
            'joinType' => 'INNER'
        ]);
        $this->belongsTo('Ledgers', [
            'foreignKey' => 'ledger_id',
            'joinType' => 'INNER'
        ]);
        $this->hasMany('ReferenceDetails', [
            'foreignKey' => 'contra_voucher_row_id'
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->integer('id')
            ->allowEmpty('id', 'create');

        

        return $validator;
    }
    public function beforeMarshal(Event $event, ArrayObject $data)
    {
		if(@$data['cheque_date']!="")
		{
			@$data['cheque_date'] = trim(date('Y-m-d',strtotime(@$data['cheque_date'])));
		}
    }
    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        $rules->add($rules->existsIn(['contra_voucher_id'], 'ContraVouchers'));
        $rules->add($rules->existsIn(['ledger_id'], 'Ledgers'));

        return $rules;
    }
}
