<?php
/*
 * © Copyright 2007, 2008 IntraHealth International, Inc.
 * 
 * This File is part of iHRIS
 * 
 * iHRIS is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * The page wrangler
 * 
 * This page loads the main HTML template for the home page of the site.
 * @package iHRIS
 * @subpackage DemoManage
 * @access public
 * @author Carl Leitner <litlfred@ibiblio.org>
 * @copyright Copyright &copy; 2007, 2008 IntraHealth International, Inc. 
 * @since Demo-v2.a
 * @version Demo-v2.a
 */

$cwd = getcwd();
$i2ce_site_user_access_init = null;
require_once( $cwd . DIRECTORY_SEPARATOR . 'config.values.php');

$local_config = $cwd . DIRECTORY_SEPARATOR .
'local' . DIRECTORY_SEPARATOR . 'config.values.php';
if (file_exists($local_config)) {
    require_once($local_config);
}

if(!isset($i2ce_site_i2ce_path) || !is_dir($i2ce_site_i2ce_path)) {
    echo "Please set the \$i2ce_site_i2ce_path in $local_config";
    exit(55);
}

putenv('nocheck=1');
require_once ($i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'I2CE_config.inc.php');
@I2CE::initializeDSN($i2ce_site_dsn,   $i2ce_site_user_access_init,    $i2ce_site_module_config);         


unset($i2ce_site_user_access_init);
unset($i2ce_site_dsn);
unset($i2ce_site_i2ce_path);
unset($i2ce_site_module_config);


//YOU SHOULD COPY OR LINK THIS INTO YOUR PAGES DIRECTORY ALONG SIDE index.php 

echo "In the iHRIS 4.2 there are several places that configuration has changed.  This tool will look at all of your currently available modules to see if there are any changes that need to be made\n";


$black = "\033[0m";
$red = "\033[31m";


$mf = I2CE_ModuleFactory::instance();
$config = I2CE::getConfig();
$modules = array();

foreach ($mf->getAvailable() as $module) {
    echo "Checking Available Modules $module\n";
    $file = false;
    if (! ($config->setIfIsSet($file,"/config/data/$module/file"))) {
        echo "\t{$red}Warning{$black}No XML file associated to this module\n";
        continue;
    }
    $storage = I2CE_MagicData::instance( "temp_$module" );
    $configurator =new I2CE_Configurator($storage);    
    if ($module != $configurator->processConfigFile($file,true,false)) {
        echo "\t{$red}Warning:{$black}XML file associated to this module is invalid at: $file\n";
        continue;
    }
    if (count($matches =  test_display_mapped_orders($storage) )> 0) { 
        echo "\t{$red}Warning: {$black}You need to move meta/display/\$form/orders to meta/display/orders/\$form at\n\t" . implode("\n\t",$matches) . "\n";
        $modules[] = $module;
    }
    if (count($matches =  test_display_mapped_printf_and_nolimits($storage) )> 0) { 
        echo "\t{$red}Warning: {$black}You need to move magic data under meta/display/\$form/\$style/\$key to meta/display/\$style/\$key/\$form at\n\t" . implode("\n\t",$matches) . "\n";
        $modules[] = $module;
    }
    if (count($matches =  test_display_reports($storage) )> 0) { 
        echo "\t{$red}Warning: {$black}You need to move magic data under meta/\$key/\$style to meta/display/\$style/\$key at\n\t" . implode("\n\t",$matches) . "\n";
        $modules[] = $module;
    }

    $configurator->__destruct();
    $configurator = null;
    $storage->erase();
    $storage = null;

}

if (count($modules) > 0) {
    echo "You need to fix the following modules (see above for the reason):" .implode(",",array_unique($modules)) . "\n";

}


// meta/display/$form/$style/printf 
// meta/display/$form/$style/printf_args
// meta/display/$form/$style/no_limits
//  meta/display/$form/$s/printf_arg_styles 
function test_display_mapped_printf_and_nolimits($storage) {
    $matches = array();
    $formClasses = $storage->getKeys("/modules/forms/formClasses");
    $forms = I2CE::getConfig()->getKeys("/modules/forms/forms");
    $keys = array('printf','printf_args','no_limits','printf_arg_styles');
    foreach ($formClasses as $formClass) {
        $fields = $storage->getKeys("/modules/forms/formClasses/$formClass/fields");
        foreach ($fields as $field) {
            foreach ($forms as $form) {
                $styles  = $storage->getKeys("/modules/forms/formClasses/$formClass/fields/$field/meta/display/$form");
                foreach ($styles as $style) {
                    foreach ($keys as $key) {
                        $path = "/modules/forms/formClasses/$formClass/fields/$field/meta/display/$form/$style/$key";
                        if (! ($node = $storage->traverse($path,false,false)) instanceof I2CE_MagicDataNode) {
                            continue;
                        }
                        $matches[] = $path;
                    }
                }
            }
        }
    }
    return $matches;
    
}


// meta/reportSelect/$style
//   meta/display_report/$type


function test_display_reports($storage) {
    $matches = array();
    $formClasses = $storage->getKeys("/modules/forms/formClasses");
    $keys = array('display_report','reportSelect');
    foreach ($formClasses as $formClass) {
        $fields = $storage->getKeys("/modules/forms/formClasses/$formClass/fields");
        foreach ($fields as $field) {
            foreach ($keys as $key) {
                $path ="/modules/forms/formClasses/$formClass/fields/$field/meta/$key";
                if (! ($node = $storage->traverse($path,false,false)) instanceof I2CE_MagicDataNode) {
                    continue;
                }
                $matches[] = $path;
            }
        }
    }
    return $matches;
    
}


function test_display_mapped_orders($storage) {
    $matches = array();
    $formClasses = $storage->getKeys("/modules/forms/formClasses");
    foreach ($formClasses as $formClass) {
        $fields = $storage->getKeys("/modules/forms/formClasses/$formClass/fields");
        foreach ($fields as $field) {
            $path ="/modules/forms/formClasses/$formClass/fields/$field/meta/display/orders";
            if (! ($node = $storage->traverse($path,false,false)) instanceof I2CE_MagicDataNode) {
                continue;
            }
            $matches[] = $path;
        }
    }
    return $matches;
    
}



# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
