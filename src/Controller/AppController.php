<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link      http://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Event\Event;
use Cake\I18n\FrozenDate;
use Cake\I18n\FrozenTime;
/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link http://book.cakephp.org/3.0/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{

    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('Security');`
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();

		
		FrozenTime::setToStringFormat('dd-MM-yyyy hh:mm a');  // For any immutable DateTime
		FrozenDate::setToStringFormat('dd-MM-yyyy');  // For any immutable Date
        $this->loadComponent('RequestHandler');
        $this->loadComponent('Flash');
		
		$this->loadComponent('Auth', [
		 'authenticate' => [
                'Form' => [
                    'fields' => [
                        'username' => 'username',
                        'password' => 'password'
                    ],
                      'userModel' => 'Users'
                ]
            ],
            'logoutRedirect' => [
                'controller' => 'Users',
                'action' => 'login'
            ],
			'unauthorizedRedirect' => $this->referer(),
        ]);
		
		if($this->Auth->User('session_company')){
			$coreVariable = [
				'company_name' => $this->Auth->User('session_company')->name,
				'fyValidFrom' => $this->Auth->User('fyValidFrom'),
				'fyValidTo' => $this->Auth->User('fyValidTo'),
				'location_name' => $this->Auth->User('location_name'),
			];
			$this->coreVariable = $coreVariable;
			$this->set(compact('coreVariable'));
		}
		
		$this->loadModel('UserRights');
		$this->loadModel('Pages');
		$userid=$this->Auth->User('id');
		if(!empty($userid)){
		$userData = $this->UserRights->find()
		            ->where(['UserRights.user_id'=>$userid])
					//->contain(['Pages'])
					->autoFields(true);
			foreach($userData->toArray() as $data)
			{
				$userPages[]=$data->page_id;
			}
			$this->set(compact('userPages'));
		}
		
		/* $pages=$this->Pages->find()->where(['master'=>1]);
		$this->set(compact('pages')); */
		$controller = $this->request->params['controller'];
		$action = $this->request->params['action']; 
		$page=$this->Pages->find()->where(['controller_name'=>$controller,'action'=>$action])->first();
		
		if(!empty($page->id) and !in_array($page->id,$userPages)){
			$pages=[];
			$this->set(compact('pages'));
			$this->viewBuilder()->layout('index_layout');
			$this -> render('/Error/pageNotFound'); 
		}
		
        /*
         * Enable the following components for recommended CakePHP security settings.
         * see http://book.cakephp.org/3.0/en/controllers/components/security.html
         */
        //$this->loadComponent('Security');
        //$this->loadComponent('Csrf');
    }
	
	public function repairRef(){    
		$this->loadModel('ReferenceDetails');
		$company_id=$this->Auth->User('session_company_id');
		$contraVouchers = $this->ReferenceDetails->find()
							->where(['ReferenceDetails.type'=>'Against','company_id'=>$company_id])
							->group(['ReferenceDetails.ref_name']);
							
		foreach($contraVouchers as $contraVoucher)
		{   
			 $totalRef = $this->ReferenceDetails->find()
			                 ->where(['ReferenceDetails.ref_name'=>$contraVoucher->ref_name,'company_id'=>$company_id,'ReferenceDetails.type'=>'New Ref'])->count(); 
			if($totalRef<1)
			{
				$query = $this->ReferenceDetails->query();
				$query->update()
					->set(['type' => 'New Ref'])
					->where(['id' => $contraVoucher->id])
					->execute();
			}
			
		}
	}
	
	public function StockValuation(){
		$this->loadModel('ItemLedgers');
		$company_id=$this->Auth->User('session_company_id');
		$ItemLedgers=$this->ItemLedgers->find()->where(['ItemLedgers.company_id'=>$company_id]);
		$stock=[];
		foreach($ItemLedgers as $ItemLedger){
			if($ItemLedger->status=="in"){
				for($inc=0;$inc<$ItemLedger->quantity;$inc++){
					$stock[$ItemLedger->item_id][]=$ItemLedger->rate;
				}
			}
		}
		foreach($ItemLedgers as $ItemLedger){
			if($ItemLedger->status=='out'){
				if(sizeof(@$stock[$ItemLedger->item_id])>0){
					$stock[$ItemLedger->item_id] = array_slice($stock[$ItemLedger->item_id], $ItemLedger->quantity); 
				}
			}
		}
		$closingValue=0;
		foreach($stock as $stockRow){
			foreach($stockRow as $stockRowRate){
				$closingValue+=$stockRowRate;
			}
		}
		return $closingValue;
	}
	
	public function StockValuationWithDate($date){
		$this->loadModel('ItemLedgers');
		$company_id=$this->Auth->User('session_company_id');
		$ItemLedgers=$this->ItemLedgers->find()->where(['ItemLedgers.company_id'=>$company_id,'ItemLedgers.transaction_date <='=>$date]);
		$stock=[];
		foreach($ItemLedgers as $ItemLedger){
			if($ItemLedger->status=="in"){
				for($inc=0;$inc<$ItemLedger->quantity;$inc++){
					$stock[$ItemLedger->item_id][]=$ItemLedger->rate;
				}
			}
		}
		foreach($ItemLedgers as $ItemLedger){
			if($ItemLedger->status=='out'){
				if(sizeof(@$stock[$ItemLedger->item_id])>0){
					$stock[$ItemLedger->item_id] = array_slice($stock[$ItemLedger->item_id], $ItemLedger->quantity); 
				}
			}
		}
		$closingValue=0;
		foreach($stock as $stockRow){
			foreach($stockRow as $stockRowRate){
				$closingValue+=$stockRowRate;
			}
		}
		return $closingValue;
	}
	
	public function GrossProfit($from_date,$to_date){
		$company_id=$this->Auth->User('session_company_id');
		$AccountingGroups=$this->AccountingEntries->Ledgers->AccountingGroups->find()->where(['AccountingGroups.nature_of_group_id IN'=>[3,4],'AccountingGroups.company_id'=>$company_id]);
		$Groups=[];
		foreach($AccountingGroups as $AccountingGroup){
			$Groups[$AccountingGroup->id]['ids'][]=$AccountingGroup->id;
			$Groups[$AccountingGroup->id]['name']=$AccountingGroup->name;
			$Groups[$AccountingGroup->id]['nature']=$AccountingGroup->nature_of_group_id;
			$accountingChildGroups = $this->AccountingEntries->Ledgers->AccountingGroups->find('children', ['for' => $AccountingGroup->id]);
			foreach($accountingChildGroups as $accountingChildGroup){
				$Groups[$AccountingGroup->id]['ids'][]=$accountingChildGroup->id;
			}
		}
		$AllGroups=[];
		foreach($Groups as $mainGroups){
			foreach($mainGroups['ids'] as $subGroup){
				$AllGroups[]=$subGroup;
			}
		}
		
		$query=$this->AccountingEntries->find();
		$query->select(['ledger_id','totalDebit' => $query->func()->sum('AccountingEntries.debit'),'totalCredit' => $query->func()->sum('AccountingEntries.credit')])
				->group('AccountingEntries.ledger_id')
				->where(['AccountingEntries.company_id'=>$company_id])
				->contain(['Ledgers'=>function($q){
					return $q->select(['Ledgers.accounting_group_id','Ledgers.id']);
				}]);
		$query->matching('Ledgers', function ($q) use($AllGroups){
			return $q->where(['Ledgers.accounting_group_id IN' => $AllGroups]);
		});
		$balanceOfLedgers=$query;
		
		$totalDr=0; $totalCr=0;
		foreach($balanceOfLedgers as $balanceOfLedger){
			$totalDr+=$balanceOfLedger->totalDebit;
			$totalCr+=$balanceOfLedger->totalCredit;
		}
		
		$openingValue= $this->StockValuationWithDate($from_date);
		$closingValue= $this->StockValuation();
		
		$totalDr+=$openingValue;
		$totalCr+=$closingValue;
		return $totalCr-$totalDr;
	}
	
	public function differenceInOpeningBalance(){
		$this->loadModel('AccountingEntries');
		$company_id=$this->Auth->User('session_company_id');
		$Ledgers=$this->AccountingEntries->find()->where(['AccountingEntries.company_id'=>$company_id, 'AccountingEntries.is_opening_balance'=>'yes']);
		
		$output=0;
		foreach($Ledgers as $Ledger){
			$output+=$Ledger->debit;
			$output-=$Ledger->credit;
		}
		return $output;
	}

    /**
     * Before render callback.
     *
     * @param \Cake\Event\Event $event The beforeRender event.
     * @return \Cake\Network\Response|null|void
     */
    public function beforeRender(Event $event)
    {
        if (!array_key_exists('_serialize', $this->viewVars) &&
            in_array($this->response->type(), ['application/json', 'application/xml'])
        ) {
			
            $this->set('_serialize', true);
        }
    }
	
	
	
}
