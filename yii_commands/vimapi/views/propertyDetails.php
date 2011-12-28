<?php if(!$class->nativePropertyCount) return; ?>


PROPERTY DETAILS
------------------------------------------------------------------------------
<?php foreach($class->properties as $property): ?>
<?php if($property->isInherited) continue; ?>

<?php echo $this->renderTitleTag('<'.$property->name.'>',$this->createTag($class->name,$property->name,'*'))."\n"; ?>
<?php echo $this->renderDescription("($property->type) ".$property->description,1); ?>

<?php endforeach; ?>
