<?php
/**
 * VimApiCommand class file.
 *
 * Command to create Yii API manual in VIM helpfile format.
 *
 * To use this command, you need the SVN version of Yii:
 *
 *	svn co http://yii.googlecode.com/svn/trunk /tmp/yii-svn
 *	cd /tmp/yii-svn/build
 *
 * You can use YII_CONSOLE_COMMANDS to point to this directory:
 *
 *	export YII_CONSOLE_COMMANDS=/path/to/yii-api-vim/yii_commands/
 *	./build vimapi /path/to/output
 *
 * @author Michael Härtl <haertl.mike@googlemail.com>
 * @link http://www.yiiframework.com/
 * @copyright Copyright &copy; 2008-2010 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */
Yii::import('application.commands.api.ApiModel');

class VimApiCommand extends CConsoleCommand
{
	const URL_PATTERN='/\{\{(.*?)\|(.*?)\}\}/s';
	const CODE_PATTERN='|\s*<pre>(.*?)</pre>\s*|is';
	const UL_PATTERN='|\s*<ul>(.*?)</ul>\s*|is';
	const OL_PATTERN='|\s*<ol>(.*?)</ol>\s*|is';
	const LI_PATTERN='|\s*<li>(.*?)</li>\s*|is';
	const A_PATTERN='|<a.*?>(.*?)</a>|is';

	// Separator between classname and property/method
	const SEP='::';

	public $classes;
	public $packages;
	public $pageTitle;
	public $themePath;
	public $currentClass;
	public $baseSourceUrl="http://code.google.com/p/yii/source/browse";
	public $version;

	// tmp storage for cut out content
	private $_preContent=array();
	private $_htmlContent=array();

	private $_olNum;
			
	public function getHelp()
	{
		return <<<EOD
USAGE
  build vimapi <output-path> 

DESCRIPTION
  This command generates API documentation in vim help file format for the Yii framework.

PARAMETERS
  * output-path: required, the directory where the generated documentation would be saved.

EXAMPLES
  * build vimapi /etc/vim/yiiapi/doc

EOD;
	}

	/**
	 * Execute the action.
	 * @param array command line parameters specific for this command
	 */
	public function run($args)
	{
		$options=array(
			'fileTypes'=>array('php'),
			'exclude'=>array(
				'.svn',
				'/yiilite.php',
				'/yiit.php',
				'/cli',
				'/i18n/data',
				'/messages',
				'/vendors',
				'/views',
				'/web/js',
				'/web/widgets/views',
				'/utils/mimeTypes.php',
				'/gii/assets',
				'/gii/components',
				'/gii/controllers',
				'/gii/generators',
				'/gii/models',
				'/gii/views',
			),
		);

		if(!isset($args[0]))
			$this->usageError('the output directory is not specified.');

		if(!is_dir($docPath=$args[0]))
			$this->usageError("the output directory {$docPath} does not exist.");

		$offline=true;
		if(isset($args[1]) && $args[1]==='online')
			$offline=false;

		$this->version=Yii::getVersion();

		/*
		 * development version - link to trunk
		 * release version link to tags
		 */
		if(substr($this->version,-3)=='dev')
			$this->baseSourceUrl .= '/trunk/framework';
		else
			$this->baseSourceUrl .= '/tags/'.$this->version.'/framework';

		$this->pageTitle='Yii Framework Class Reference for vim';
		$themePath=dirname(__FILE__).'/vimapi';

		echo "\nBuilding.. : " . $this->pageTitle."\n";
		echo "Version... : " . $this->version."\n";
		echo "Source URL : " . $this->baseSourceUrl."\n\n";

		echo "Building model...\n";
		$model=$this->buildModel(YII_PATH,$options);
		$this->classes=$model->classes;
		$this->packages=$model->packages;

		echo "Building help files...\n";
		$this->buildVimHelpFiles($docPath,$themePath);
		echo "Done.\n\n";
	}

	protected function buildModel($sourcePath,$options)
	{
		$files=CFileHelper::findFiles($sourcePath,$options);
		$model=new ApiModel;
		$model->build($files);
		return $model;
	}

	protected function buildVimHelpFiles($docPath,$themePath)
	{
		$this->themePath=$themePath;
		@mkdir($docPath);

		foreach($this->classes as $name=>$class)
		{
			$this->currentClass=$name;
			$this->pageTitle=$name;
			$content=$this->renderPartial('class',array('class'=>$class),true);
			file_put_contents($docPath.'/'.$name.'.txt',$content);
		}
	}

	public function renderPartial($view,$data=null,$return=false)
	{
		$viewFile=$this->themePath."/views/{$view}.php";
		return $this->renderFile($viewFile,$data,$return);
	}

	/**
	 * Render a line with title on the left and tag on position $column. Fills
	 * up space between with as many tabs and as few spaces as possible.
	 *
	 * @param string $title on the left
	 * @param string $tag on the right, including '*'s
	 * @param int $column position where tag should start
	 * @param int $tabstop width of a single tab (spaces)
	 * @return string string with as many tabs as possible between title and tag
	 */
	public function renderTitleTag($title,$tag,$column=40,$tabstop=8) 
	{
		$out=sprintf("%-{$column}s%s",$title,$tag);	// first use spaces
		return $this->spaces2tabs($out,$tabstop);	// then convert to tabs
	}

	/**
	 * @param mixed $class 
	 * @return string the inheritance "breadcrumb", separated with ' >> '
	 */
	public function renderInheritance($class)
	{
		$parents=array();
		foreach($class->parentClasses as $parent)
			$parents[]='|'.$parent.'|';
		return count($parents) ? ' >> '.implode(' >> ',$parents) : '';
	}

	/**
	 * Render method signature including param defaults
	 * 
	 * @param MethodDoc $method model
	 * @return string the formatted signature
	 */
	public function renderMethodSignature($method)
	{
		$params=array();
		foreach($method->input as $param)
			$params[]='$'.$param->name.$this->paramDefault($param);
		
		return $method->name.'('.implode(', ',$params).')';
	}

	/**
	 * Render description comment
	 * 
	 * @param mixed $description 
	 * @param int $indent indent text to this column
	 * @return string the rendered description
	 */
	public function renderDescription($content,$indent=0,$wrap=78)
	{
		$content=$this->processHtml($content);
		$content=$this->wrapindent($content,$indent,$wrap);
		$content=$this->postProcessPretags($content);
		return $content;
	}

	/**
	 * Render a single method parameter including code examples
	 * 
	 * @param ParamDoc $param model
	 * @param int $indent how many columns to indent the string
	 * @param int $wrap number of columns (incl. indent) of a line
	 * @return string the formated text block for a parameter
	 */
	public function renderMethodParam($param,$indent=0,$wrap=78)
	{
		$content=$this->processHtml($param->description);
		$content="[$param->name] ($param->type) ".$content;
		$content=$this->wrapindent($content,$indent,$wrap);
		$content=$this->postProcessPretags($content);
		return $content;
	}

	/**
	 * Render the return parameter
	 * 
	 * @param mixed $param 
	 * @param int $indent how many columns to indent the string
	 * @param int $wrap number of columns (incl. indent) of a line
	 * @return string the formated text block of a return value
	 */
	public function renderMethodReturnValue($param,$indent=0,$wrap=78)
	{
		if (empty($param))
			return "(void)\n";
		$content=$this->processHtml($param->description);
		$content="($param->type) $content\n";
		$content=$this->wrapindent($content,$indent,$wrap);
		$content=$this->postProcessPretags($content);
		return $content;
	}

	/**
	 * @param string $class name of the class
	 * @param mixed $property optional property/method name
	 * @param string $surround string to put around created tag (default '')
	 * @access public
	 * @return string name of the vim tag
	 */
	public function createTag($class,$property=null,$surround='')
	{
		if ($property===null)
			return $surround.$class.$surround;
		return $surround.$class.self::SEP.$property.$surround;
	}

	/**
	 * Converts HTML to formatted text
	 *
	 * Leaves <pre>..</pre> blocks intact and makes sure these tags are
	 * on a separate line. Call fixPreTags() to replace them with > and < for vim.
	 *
	 * @param string $content 
	 * @return string the convertet comment
	 */
	protected function processHtml($content)
	{
		// Cut out and store content inside <pre>..</pre>, replace with placeholder
		$content=preg_replace_callback(self::CODE_PATTERN,array($this,'convertPretags2Placeholder'),$content);

		// Replace all linebreaks an <br/>
		$content=trim(strtr($content,array("\n"=>' ','<br/>'=>"\n")));

		// Remove spaces at start/end of lines
		$content=preg_replace('/(^ *| *$)/m','',$content);

		// Convert <code>
		$content=preg_replace('|<code>(.*?)</code>|si',"'\\1'",$content);

		// Convert <a>
		$content=preg_replace(self::A_PATTERN,"'\\1'",$content);

		// Convert references to vim tags
		$content=preg_replace_callback(self::URL_PATTERN,array($this,'convertRefs2Vimtags'),$content);

		// <ul> and <ol>
		$content=preg_replace_callback(self::UL_PATTERN,array($this,'convertULtags'),$content);
		$content=preg_replace_callback(self::OL_PATTERN,array($this,'convertOLtags'),$content);

		// Now put back code examples in same order
		$content=preg_replace_callback('/###CODE###/',array($this,'convertPlaceholder2Pretags'),$content);

		return $content;
	}

	/**
	 * @param ParamDoc $param model
	 * @return string the parameter signature
	 */
	protected function paramDefault($param) 
	{
		if (!$param->isOptional)
			return '';

		switch($param->type) {
			case 'string': return "='$param->defaultValue'";
			case 'integer': 
			case 'int': return '='.$param->defaultValue;
			default: 
				return '='.strtr(
					var_export($param->defaultValue,true),
					array("\n"=>'',' '=>'')
				);
		}
	}

	/**
	 * @param string $text to indent and wrap
	 * @param mixed $indent how many columns to indent
	 * @param mixed $wrap total column count (incl. indent)
	 * @return string the text indented with spaces and wrapped
	 */
	protected function wrapindent($text,$indent,$wrap)
	{
		$text=wordwrap($text,$wrap-$indent,"\n");
		return preg_replace('/^(?=.+)/m',sprintf("%{$indent}s",''),$text);
	}

	/**
	 * @param string text where start is aligned to a tab or left border
	 * @param int $ts tabstop (default 8)
	 * @return string text with as many spaces as possible replaced with tabs
	 */
	protected function spaces2tabs($text,$ts=8)
	{
		$out='';
		foreach(str_split($text,$ts) as $n=>$chunk)
		{
			$chunk=rtrim($chunk,' ');
			if (!isset($chunk[$ts-1]))	// last char stripped off
				// append \t if more than 2 spaces where cut off.
				// add back single space if only 1 space was cut off
				$out.=($chunk . (isset($chunk[$ts-2]) ? ' ':"\t"));
			else
				$out.=$chunk;
		}
		return rtrim($out);
	}

	/**
	 * Should be called after all other formatting was done to replace
	 * any code blocks in <pre> tags with blocks enclosed in '>' and '<'
	 * on the beginning of a new line.
	 * 
	 * @param string $content with <pre>...</pre> code blocks
	 * @access protected
	 * @return string with code blocks formatted for vim help files
	 */
	protected function postProcessPretags($content)
	{
		return preg_replace(
			array('/^\s*<pre>\s*$/m','|^\s*</pre>\s*$|m'),
			array('>','<'),
			$content
		);
	}

	/**
	 * @param string $content a multi line string
	 * @access protected
	 * @return string with empty lines at beginning and end deleted
	 */
	protected function stripEmptyLines($content)
	{
		return preg_replace('/(^\s*\n|\n\s*$)/','',$content);
	}

	/**
	 * @param array $matches from a preg_replace_callback call
	 * @return string the references (links) converted to vim tags enclosed in '|'
	 */
	protected function convertRefs2Vimtags($matches)
	{
		if(($pos=strpos($matches[1],'::'))!==false)
		{
			$className=substr($matches[1],0,$pos);
			$method=substr($matches[1],$pos+2);
			if($className!=='index')
				return $this->createTag($className,$method,'|');
		}
		elseif($matches[1]!=='index')
				return $this->createTag($matches[1],null,'|');
	}

	/**
	 * @param array $matches from a preg_replace_callback call
	 * @return string text formated UL list
	 */
	protected function convertULtags($matches)
	{
		$li=$this->stripEmptyLines($matches[1]);
		return preg_replace_callback(self::LI_PATTERN,array($this,'convertLItags'),$li);
	}

	/**
	 * @param array $matches from a preg_replace_callback call
	 * @return string text formated OL list
	 */
	protected function convertOLtags($matches)
	{
		$li=$this->stripEmptyLines($matches[1]);
		$li=$matches[1];
		$this->_olNum=1;
		$content=preg_replace_callback(self::LI_PATTERN,array($this,'convertLItags'),$li);
		$this->_olNum=null;
		return "\n".$content."\n\n";
	}

	/**
	 * @param array $matches from a preg_replace_callback call
	 * @return string text formated LI list, optional with number if inside <ol>
	 */
	protected function convertLItags($matches)
	{
		$content=$this->stripEmptyLines($matches[1]);
		$content=preg_replace('/^(\w+):/','{\\1}',trim($content));
		if ($this->_olNum!==null)
			$content=($this->_olNum++).". $content";
		return "\n".$this->wrapindent($content,2,68);
	}

	/**
	 * Marks all code blocks and stores the found snippets.
	 *
	 * @param array $matches from a preg_replace_callback call
	 * @return string code placeholder '###CODE###'
	 */
	protected function convertPretags2Placeholder($matches)
	{
		$code=$this->stripEmptyLines($matches[1]);
		$this->_preContent[]=htmlspecialchars_decode($code);
		return '###CODE###';
	}

	/**
	 * @param array $matches from a preg_replace_callback call
	 * @return string the stored code block
	 */
	protected function convertPlaceholder2Pretags($matches)
	{
		return "\n<pre>\n".array_shift($this->_preContent)."\n</pre>\n";
	}
		
}
