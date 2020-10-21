<?php
class MemberViewModel extends ViewModel {
   public $viewFields = array(
   'member'=>array('id','username','status','money','sex','province','city','birthday','qq','email','regtime','loginip','rankid','_type'=>'LEFT'),
   'member_rank'=>array('rankname','rankmoney','rankimg','groupid','_on'=>'member.rankid = member_rank.id'), 
   );
   }