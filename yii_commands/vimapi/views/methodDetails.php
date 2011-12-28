<?php if(!$class->nativeMethodCount) return; ?>


METHOD DETAILS
------------------------------------------------------------------------------
<?php foreach($class->methods as $method): ?>
<?php if($method->isInherited) continue; ?>

<?php echo $this->renderTitleTag(
	$this->createTag($class->name,$method->name,'*'),
	$method->isProtected ? 'protected':'public'
	,65
)."\n>\n"; ?>
 <?php echo $this->renderMethodSignature($method)."\n<\n"; ?>
<?php echo $this->renderMethodReturnValue($method->output)."\n"; ?>
<?php echo empty($method->description) ? '':$this->renderDescription($method->description,1)."\n"; ?>
<?php foreach($method->input as $input): ?>
<?php echo $this->renderMethodParam($input,4)."\n" ?>
<?php endforeach; ?>

<?php endforeach; ?>
