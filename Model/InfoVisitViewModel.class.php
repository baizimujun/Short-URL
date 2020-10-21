<?php
class InfoVisitViewModel extends ViewModel {
   public $viewFields = array(
   'visit'=>array('id','url','visitip','visittime','from'=>'cfrom','_type'=>'LEFT'),
   'info'=>array('mid','_on'=>'info.tinyurl = visit.url'), 
   );
   }