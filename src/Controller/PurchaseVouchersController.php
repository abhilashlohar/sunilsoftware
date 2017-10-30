<?php
namespace App\Controller;

use App\Controller\AppController;

/**
 * PurchaseVouchers Controller
 *
 * @property \App\Model\Table\PurchaseVouchersTable $PurchaseVouchers
 *
 * @method \App\Model\Entity\PurchaseVoucher[] paginate($object = null, array $settings = [])
 */
class PurchaseVouchersController extends AppController
{

    /**
     * Index method
     *
     * @return \Cake\Http\Response|void
     */
    public function index()
    {
		$this->viewBuilder()->layout('index_layout');
		$company_id=$this->Auth->User('session_company_id');
		$this->paginate = [
            'contain' => []
        ];
        $purchaseVouchers = $this->paginate($this->PurchaseVouchers->find()->where(['PurchaseVouchers.company_id'=>$company_id]));

        $this->set(compact('purchaseVouchers'));
        $this->set('_serialize', ['purchaseVouchers']);
    }

    /**
     * View method
     *
     * @param string|null $id Purchase Voucher id.
     * @return \Cake\Http\Response|void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
		$this->viewBuilder()->layout('index_layout');
        $purchaseVoucher = $this->PurchaseVouchers->get($id, [
            'contain' => ['PurchaseVoucherRows'=>['Ledgers','ReferenceDetails']]
        ]);

        $this->set('purchaseVoucher', $purchaseVoucher);
        $this->set('_serialize', ['purchaseVoucher']);
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
		$this->viewBuilder()->layout('index_layout');
		$purchaseVoucher = $this->PurchaseVouchers->newEntity();
		$company_id=$this->Auth->User('session_company_id');
		if ($this->request->is('post')) 
		{
			$purchaseVoucher = $this->PurchaseVouchers->patchEntity($purchaseVoucher, $this->request->getData());
			$Voucher_no = $this->PurchaseVouchers->find()->select(['voucher_no'])->where(['company_id'=>$company_id])->order(['voucher_no' => 'DESC'])->first();
			if($Voucher_no)
			{
				$purchaseVoucher->voucher_no = $Voucher_no->voucher_no+1;
			}
			else
			{
				$purchaseVoucher->voucher_no = 1;
			} 
			$purchaseVoucher->company_id            = $company_id;
			
			$purchaseVoucher = $this->PurchaseVouchers->patchEntity($purchaseVoucher, $this->request->getData(), [
							'associated' => ['PurchaseVoucherRows','PurchaseVoucherRows.ReferenceDetails']
						]);
			if(!empty($purchaseVoucher->supplier_invoice_date))
			{
				$purchaseVoucher->supplier_invoice_date = date("Y-m-d",strtotime($purchaseVoucher->supplier_invoice_date));
			}
			$purchaseVoucher->transaction_date      = date("Y-m-d",strtotime($purchaseVoucher->transaction_date));
			foreach($purchaseVoucher->purchase_voucher_rows as $purchase_voucher_row)
			{
				if(!empty($purchase_voucher_row->reference_details))
				{
					foreach($purchase_voucher_row->reference_details as $reference_detail)
					{
						$reference_detail->transaction_date = $purchaseVoucher->transaction_date;
					}
				}
			}
			//pr($purchaseVoucher);exit;
			if ($this->PurchaseVouchers->save($purchaseVoucher)) 
			{
				foreach($purchaseVoucher->purchase_voucher_rows as $purchase_voucher_row)
				{
					$accountEntry = $this->PurchaseVouchers->AccountingEntries->newEntity();
					$accountEntry->ledger_id                  = $purchase_voucher_row->ledger_id;
					$accountEntry->debit                      = $purchase_voucher_row->debit;
					$accountEntry->credit                     = $purchase_voucher_row->credit;
					$accountEntry->transaction_date           = $purchaseVoucher->transaction_date;
					$accountEntry->company_id                 = $company_id;
					$accountEntry->purchase_voucher_id        = $purchaseVoucher->id;
					$accountEntry->purchase_voucher_row_id    = $purchase_voucher_row->id;
					
					$this->PurchaseVouchers->AccountingEntries->save($accountEntry);
				}
				
				$this->Flash->success(__('The purchase voucher has been saved.'));

				return $this->redirect(['action' => 'add']);
			}
			$this->Flash->error(__('The purchase voucher could not be saved. Please, try again.'));
		}
		$Voucher_no = $this->PurchaseVouchers->find()->select(['voucher_no'])->where(['company_id'=>$company_id])->order(['voucher_no' => 'DESC'])->first();
		if($Voucher_no)
		{
			$voucher_no=$Voucher_no->voucher_no+1;
		}
		else
		{
			$voucher_no=1;
		} 

		$bankParentGroups = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->AccountingGroups->find()
						->where(['AccountingGroups.company_id'=>$company_id, 'AccountingGroups.bank'=>'1']);
						
		$bankGroups=[];
		
		foreach($bankParentGroups as $bankParentGroup)
		{
			$accountingGroups = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->AccountingGroups
			->find('children', ['for' => $bankParentGroup->id])->toArray();
			$bankGroups[]=$bankParentGroup->id;
			foreach($accountingGroups as $accountingGroup){
				$bankGroups[]=$accountingGroup->id;
			}
		}
		
		$accountGroupCreditParents = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->AccountingGroups->find()->where(['purchase_voucher_first_ledger'=>1,'company_id'=>$company_id]); 
		$CreditGroups=[];
		foreach($accountGroupCreditParents as $accountGroupCreditParent)
		{ 
			$ChildGroups = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->AccountingGroups->find('children', ['for' =>$accountGroupCreditParent->id])->toArray();
			
			$CreditGroups[]=$accountGroupCreditParent->id;
			foreach($ChildGroups as $ChildGroup)
			{
				$CreditGroups[]=$ChildGroup->id;
			}
		}
		$AllCreditledgers = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->find()->where(['Ledgers.accounting_group_id IN' =>$CreditGroups]);
		
		$Creditledgers=[];
		foreach($AllCreditledgers as $AllCreditledger){
		if(in_array($AllCreditledger->accounting_group_id,$bankGroups)){
				$Creditledgers[]=['text' =>$AllCreditledger->name, 'value' => $AllCreditledger->id ,'open_window' => 'bank'];
			}
			else if($AllCreditledger->bill_to_bill_accounting == 'yes'){
				$Creditledgers[]=['text' =>$AllCreditledger->name, 'value' => $AllCreditledger->id,'open_window' => 'party' ];
			}
			else{
				$Creditledgers[]=['text' =>$AllCreditledger->name, 'value' => $AllCreditledger->id,'open_window' => 'no' ];
			}
		}
		
		
		$accountGroupdebits = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->AccountingGroups->find()->where(['purchase_voucher_purchase_ledger'=>1,'company_id'=>$company_id]);
        
		$DebitGroups=[];
		foreach($accountGroupdebits as $accountGroupdebit)
		{ 
			$AllChildGroups = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->AccountingGroups->find('children', ['for' =>$accountGroupdebit->id])->toArray();
			$DebitGroups[]=$accountGroupdebit->id;
			foreach($AllChildGroups as $AllChildGroups)
			{
				$DebitGroups[]=$AllChildGroups->id;
			}
		}
		
		$AllDebitledgers = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->find()->where(['Ledgers.accounting_group_id IN' =>$DebitGroups]);
		
		$Debitledgers=[];
		foreach($AllDebitledgers as $AllDebitledger){
		if(in_array($AllDebitledger->accounting_group_id,$bankGroups)){
				$Debitledgers[]=['text' =>$AllDebitledger->name, 'value' => $AllDebitledger->id ,'open_window' => 'bank'];
			}
			else if($AllDebitledger->bill_to_bill_accounting == 'yes'){
				$Debitledgers[]=['text' =>$AllDebitledger->name, 'value' => $AllDebitledger->id,'open_window' => 'party' ];
			}
			else{
				$Debitledgers[]=['text' =>$AllDebitledger->name, 'value' => $AllDebitledger->id,'open_window' => 'no' ];
			}
		}
		
		$accountGroupParents = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->AccountingGroups->find()->where(['purchase_voucher_all_ledger'=>1,'company_id'=>$company_id]);
        
		$Groups=[];
		foreach($accountGroupParents as $accountGroupParent)
		{ 
			$Childs = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->AccountingGroups->find('children', ['for' =>$accountGroupParent->id])->toArray();
			$Groups[]=$accountGroupParent->id;
			foreach($Childs as $Child)
			{
				$Groups[]=$Child->id;
			}
		}
		
		$Allledgers = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->find()->where(['Ledgers.accounting_group_id IN' =>$Groups]);
		
		$ledgers=[];
		foreach($Allledgers as $Allledger){
		if(in_array($Allledger->accounting_group_id,$bankGroups)){
				$ledgers[]=['text' =>$Allledger->name, 'value' => $Allledger->id ,'open_window' => 'bank'];
			}
			else if($Allledger->bill_to_bill_accounting == 'yes'){
				$ledgers[]=['text' =>$Allledger->name, 'value' => $Allledger->id,'open_window' => 'party' ];
			}
			else{
				$ledgers[]=['text' =>$Allledger->name, 'value' => $Allledger->id,'open_window' => 'no' ];
			}
		}
		
		$this->set(compact('purchaseVoucher','voucher_no','Creditledgers','Debitledgers','ledgers','company_id'));
		$this->set('_serialize', ['purchaseVoucher']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Purchase Voucher id.
     * @return \Cake\Http\Response|null Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
		$this->viewBuilder()->layout('index_layout');
        $purchaseVoucher = $this->PurchaseVouchers->get($id, [
            'contain' => ['PurchaseVoucherRows'=>['Ledgers','ReferenceDetails']]
        ]);
		$company_id=$this->Auth->User('session_company_id');
		$originalPurchaseVoucher=$purchaseVoucher;
        if ($this->request->is(['patch', 'post', 'put'])) {
			
			//GET ORIGINAL DATA AND DELETE REFERENCE DATA//
			$orignalPurchase_voucher_row_ids=[];
			foreach($originalPurchaseVoucher->purchase_voucher_rows as $originalpurchase_voucher_rows){
				$orignalPurchase_voucher_row_ids[]=$originalpurchase_voucher_rows->id;
			}
			$this->PurchaseVouchers->PurchaseVoucherRows->ReferenceDetails->deleteAll(['ReferenceDetails.purchase_voucher_row_id IN'=>$orignalPurchase_voucher_row_ids]);
			//GET ORIGINAL DATA AND DELETE REFERENCE DATA//
			
            $purchaseVoucher = $this->PurchaseVouchers->patchEntity($purchaseVoucher, $this->request->getData());
			$purchaseVoucher = $this->PurchaseVouchers->patchEntity($purchaseVoucher, $this->request->getData(), [
							'associated' => ['PurchaseVoucherRows','PurchaseVoucherRows.ReferenceDetails']
						]);
			if(!empty($purchaseVoucher->supplier_invoice_date))
			{
				$purchaseVoucher->supplier_invoice_date = date("Y-m-d",strtotime($purchaseVoucher->supplier_invoice_date));
			}
			$purchaseVoucher->transaction_date      = date("Y-m-d",strtotime($purchaseVoucher->transaction_date));
			foreach($purchaseVoucher->purchase_voucher_rows as $purchase_voucher_row)
			{
				if(!empty($purchase_voucher_row->reference_details))
				{
					foreach($purchase_voucher_row->reference_details as $reference_detail)
					{
						$reference_detail->transaction_date = $purchaseVoucher->transaction_date;
					}
				}
			}
			//pr($purchaseVoucher->toArray());exit;
		    if ($this->PurchaseVouchers->save($purchaseVoucher)) {
				$query_delete = $this->PurchaseVouchers->AccountingEntries->query();
					$query_delete->delete()
					->where(['purchase_voucher_id' => $purchaseVoucher->id,'company_id'=>$company_id])
					->execute();
				foreach($purchaseVoucher->purchase_voucher_rows as $purchase_voucher_row)
				{
					$accountEntry = $this->PurchaseVouchers->AccountingEntries->newEntity();
					$accountEntry->ledger_id                  = $purchase_voucher_row->ledger_id;
					$accountEntry->debit                      = $purchase_voucher_row->debit;
					$accountEntry->credit                     = $purchase_voucher_row->credit;
					$accountEntry->transaction_date           = $purchaseVoucher->transaction_date;
					$accountEntry->company_id                 = $company_id;
					$accountEntry->purchase_voucher_id        = $purchaseVoucher->id;
					$accountEntry->purchase_voucher_row_id    = $purchase_voucher_row->id;
					
					$this->PurchaseVouchers->AccountingEntries->save($accountEntry);
				}
                $this->Flash->success(__('The purchase voucher has been saved.'));
				$this->repairRef();
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The purchase voucher could not be saved. Please, try again.'));
        }
		$refDropDown =[];
		foreach($purchaseVoucher->purchase_voucher_rows as $purchase_voucher_row)
		{
			if(!empty($purchase_voucher_row->reference_details))
			{
				$query = $this->PurchaseVouchers->PurchaseVoucherRows->ReferenceDetails->find();
				$query->select(['total_debit' => $query->func()->sum('ReferenceDetails.debit'),'total_credit' => $query->func()->sum('ReferenceDetails.credit')])
				->where(['ReferenceDetails.ledger_id'=>$purchase_voucher_row->ledger_id,'ReferenceDetails.type !='=>'On Account'])
				->group(['ReferenceDetails.ref_name'])
				->autoFields(true);
				$referenceDetails=$query;
				$option=[];
				foreach($referenceDetails as $referenceDetail){
					$remider=$referenceDetail->total_debit-$referenceDetail->total_credit;
					if($remider>0){
						$bal=abs($remider).' Dr';
					}else if($remider<0){
						$bal=abs($remider).' Cr';
					}
					if($referenceDetail->total_debit!=$referenceDetail->total_credit){
						$option[] = ['text' =>$referenceDetail->ref_name.' ('.$bal.')', 'value' => $referenceDetail->ref_name];
						 
					}
				}
				
				$refDropDown[$purchase_voucher_row->id] = $option;
			}
		}
		
		$bankParentGroups = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->AccountingGroups->find()
						->where(['AccountingGroups.company_id'=>$company_id, 'AccountingGroups.bank'=>'1']);
						
		$bankGroups=[];
		
		foreach($bankParentGroups as $bankParentGroup)
		{
			$accountingGroups = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->AccountingGroups
			->find('children', ['for' => $bankParentGroup->id])->toArray();
			$bankGroups[]=$bankParentGroup->id;
			foreach($accountingGroups as $accountingGroup){
				$bankGroups[]=$accountingGroup->id;
			}
		}
		
		$accountGroupCreditParents = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->AccountingGroups->find()->where(['purchase_voucher_first_ledger'=>1,'company_id'=>$company_id]); 
		$CreditGroups=[];
		foreach($accountGroupCreditParents as $accountGroupCreditParent)
		{ 
			$ChildGroups = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->AccountingGroups->find('children', ['for' =>$accountGroupCreditParent->id])->toArray();
			
			$CreditGroups[]=$accountGroupCreditParent->id;
			foreach($ChildGroups as $ChildGroup)
			{
				$CreditGroups[]=$ChildGroup->id;
			}
		}
		$AllCreditledgers = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->find()->where(['Ledgers.accounting_group_id IN' =>$CreditGroups,'company_id'=>$company_id]);
		
		$Creditledgers=[];
		foreach($AllCreditledgers as $AllCreditledger){
		if(in_array($AllCreditledger->accounting_group_id,$bankGroups)){
				$Creditledgers[]=['text' =>$AllCreditledger->name, 'value' => $AllCreditledger->id ,'open_window' => 'bank'];
			}
			else if($AllCreditledger->bill_to_bill_accounting == 'yes'){
				$Creditledgers[]=['text' =>$AllCreditledger->name, 'value' => $AllCreditledger->id,'open_window' => 'party' ];
			}
			else{
				$Creditledgers[]=['text' =>$AllCreditledger->name, 'value' => $AllCreditledger->id,'open_window' => 'no' ];
			}
		}
		
		
		$accountGroupdebits = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->AccountingGroups->find()->where(['purchase_voucher_purchase_ledger'=>1,'company_id'=>$company_id]);
        
		$DebitGroups=[];
		foreach($accountGroupdebits as $accountGroupdebit)
		{ 
			$AllChildGroups = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->AccountingGroups->find('children', ['for' =>$accountGroupdebit->id])->toArray();
			$DebitGroups[]=$accountGroupdebit->id;
			foreach($AllChildGroups as $AllChildGroups)
			{
				$DebitGroups[]=$AllChildGroups->id;
			}
		}
		
		$AllDebitledgers = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->find()->where(['Ledgers.accounting_group_id IN' =>$DebitGroups]);
		
		$Debitledgers=[];
		foreach($AllDebitledgers as $AllDebitledger){
		if(in_array($AllDebitledger->accounting_group_id,$bankGroups)){
				$Debitledgers[]=['text' =>$AllDebitledger->name, 'value' => $AllDebitledger->id ,'open_window' => 'bank'];
			}
			else if($AllDebitledger->bill_to_bill_accounting == 'yes'){
				$Debitledgers[]=['text' =>$AllDebitledger->name, 'value' => $AllDebitledger->id,'open_window' => 'party' ];
			}
			else{
				$Debitledgers[]=['text' =>$AllDebitledger->name, 'value' => $AllDebitledger->id,'open_window' => 'no' ];
			}
		}
		
		$accountGroupParents = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->AccountingGroups->find()->where(['purchase_voucher_all_ledger'=>1,'company_id'=>$company_id]);
        
		$Groups=[];
		foreach($accountGroupParents as $accountGroupParent)
		{ 
			$Childs = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->AccountingGroups->find('children', ['for' =>$accountGroupParent->id])->toArray();
			$Groups[]=$accountGroupParent->id;
			foreach($Childs as $Child)
			{
				$Groups[]=$Child->id;
			}
		}
		
		$Allledgers = $this->PurchaseVouchers->PurchaseVoucherRows->Ledgers->find()->where(['Ledgers.accounting_group_id IN' =>$Groups]);
		
		$ledgers=[];
		foreach($Allledgers as $Allledger){
		if(in_array($Allledger->accounting_group_id,$bankGroups)){
				$ledgers[]=['text' =>$Allledger->name, 'value' => $Allledger->id ,'open_window' => 'bank'];
			}
			else if($Allledger->bill_to_bill_accounting == 'yes'){
				$ledgers[]=['text' =>$Allledger->name, 'value' => $Allledger->id,'open_window' => 'party' ];
			}
			else{
				$ledgers[]=['text' =>$Allledger->name, 'value' => $Allledger->id,'open_window' => 'no' ];
			}
		}
		
        $this->set(compact('purchaseVoucher','Creditledgers','Debitledgers','ledgers','company_id','refDropDown'));
        $this->set('_serialize', ['purchaseVoucher']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Purchase Voucher id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $purchaseVoucher = $this->PurchaseVouchers->get($id);
        if ($this->PurchaseVouchers->delete($purchaseVoucher)) {
            $this->Flash->success(__('The purchase voucher has been deleted.'));
        } else {
            $this->Flash->error(__('The purchase voucher could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
