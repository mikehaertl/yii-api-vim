# Yii API plugin for VIM

This is the API manual of the PHP framework Yii as a VIM plugin.

## Installation

Download the files from https://github.com/mikehaertl/yii-api-vim/tags, extract
the package and move the `docs/` directory to your `~/.vim` directory.

Alternatively you can use a plugin manager like [Vundle](https://github.com/gmarik/vundle) (recommended).

If the help is not available after installation, you can try to issue this command in VIM:

```vim
:helptags ~/.vimrc/doc
```

## Use

There's no real configuration required for this plugin. After installing it, you can
ask for help on Yii classes like this:

```vim
:help CWebUser
```

If you want to use keyword search (which allows to press `<S-k>` over any keyword)
then you should add this line to your `.vimrc`:

```vim
autocmd BufNewFile,Bufread *.php set keywordprg="help"
```

## Create custom version

The package also contains the Yii command that was used to create the help files.
This command requires a SVN copy of Yii. To use it follow these steps:

```sh
svn co http://yii.googlecode.com/svn/trunk /tmp/yii-svn
cd /tmp/yii-svn/build

export YII_CONSOLE_COMMANDS=/path/to/yii-api-vim/yii_commands/
./build vimapi /tmp/yii-api-vim/doc
```

Again you may have to call `:helptags /tmp/yii-api-vim/doc` after the above command.
