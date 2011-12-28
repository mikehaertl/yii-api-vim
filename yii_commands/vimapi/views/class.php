<?php
$l=strlen($class->name)+2; 
echo "\n*".$class->name.'*  '.$this->renderInheritance($class);
printf("\n%'={$l}s\n",''); 
?>

<?php echo $this->renderDescription($class->description,1); ?>

<?php $this->renderPartial('propertyDetails',array('class'=>$class)); ?>

<?php $this->renderPartial('methodDetails',array('class'=>$class)); ?>


 vim:tw=78:ts=8:ft=help:norl:
