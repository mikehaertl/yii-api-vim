<?php if(!$class->nativePropertyCount) return; ?>


PROPERTY DETAILS
------------------------------------------------------------------------------
<?php foreach($class->properties as $property): ?>

<?php echo $this->renderTitleTag('<'.$property->name.'>',$this->createTag($class->name,$property->name,'*'))."\n"; ?>
<?php if($property->isInherited): ?>
 See |<?php echo $property->definedBy.'::'.$property->name ?>|
<?php else: ?>
<?php echo $this->renderDescription("($property->type) ".$property->description,1); ?>

<?php endif; ?>
<?php endforeach; ?>
