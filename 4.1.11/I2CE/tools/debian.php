#!/usr/bin/php
<?php
/*
 * © Copyright 2010 IntraHealth International, Inc.
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
 * Translate Templates
 *
 * @package I2CE
 * @subpackage Core
 * @author Carl Leitner <litlfred@ibiblio.org>
 * @copyright Copyright &copy; 2008, 2008 IntraHealth International, Inc. 
 * @version 1.0
 */


$whoami = trim(`whoami`);
$ppa = 'ihris';

$base_dir = getcwd();
$i2ce_dir = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..') . DIRECTORY_SEPARATOR;


$def_locale_file = 'modules/Pages/modules/LocaleSelector/modules/DefaultLocales/DefaultLocales.xml';
$locale_cmd = 'grep "/value" ' . $i2ce_dir .  $def_locale_file . '  | awk -F\> \'{print $2}\' | awk -F: \'{print $1}\'';
$locales = array();
exec($locale_cmd,$locales);
foreach ($locales as $i=>&$loc) {
    $loc = trim($loc);
    if (!$loc) {
        unset($locales[$i]);
    }
}
unset($loc);
$locales = implode(",",$locales);


$search_dirs = array();
$booleans['prompt'] = true;
$booleans['only-new'] = null;
$booleans['release-only'] = null;
$booleans['translate'] = null;
$booleans['skip-updates'] = false;
$booleans['skip-launchpad'] = false;
$keyid = false;
$ubuntus = array('lucid','maverick','natty','oneiric');
$launchpad_login = false;
$only_modules = array();
$rc= '';
$usage[] = 
    "[--ppa=XXX] PPA to use\n".
    "\tDefaults to $ppa\n"; 
$usage[] = 
    "[--rc=XXXX] String used to indicate a relase candiated e.g. 'rc1'\n".
    "\tDefaults to ''\n"; 
$usage[] = 
    "[--pkg-modules=XXXX,YYYY] set to comma separate listed of modules to package for\n".
    "\tDefaults do all available modules\n"; 
$usage[] = 
    "[--release-only=T/F] set to true to package only up to the last tagged release revision\n".
    "\tNo default\n"; 

$usage[] = 
    "[--translate=T/F] set to tue to translate on the following locales: $locales\n".
    "\tNo Default\n"; 
$usage[] = 
    "[--ubuntus=XXXX,YYYY] set to comma separate listed of ubunutu disitributions to package for\n".
    "\tDefaults to " . implode("," ,$ubuntus)  ."\n"; 
$usage[] = 
    "[--prompt=T/F] set to false to never prompt on actions\n".
    "\tDefaults to true\n"; 
$usage[] = 
    "[--only-new=T/F] set to true to only create modules that have changed since the previous release\n".
    "\tNo default\n"; 
$usage[] = 
    "[--skip-updates=T/F] set to true skip updating branches\n".
    "\tDefaults to false\n"; 
$usage[] = 
    "[--skip-launchpad=T/F] set to true skip updating launchpad PPA\n".
    "\tDefaults to false\n"; 
$usage[] = 
    "[--launchpad-login=T/F] set to login name/team for PPA\n";
$usage[] = 
    "[--keyid=XXXX] GPG Key to use\n";

require_once( $i2ce_dir. DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'launchpad_base.php');    
require_once( $i2ce_dir. DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'base.php');

require_once( $i2ce_dir. DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'I2CE_Validate.php');


/////////////////END CHANGED





foreach ($args as $key=>$val) {
    switch($key) {
    case 'ppa':
        $ppa = $val;
        break;
    case 'keyid':
        $keyid = $val;
        break;
    case 'launchpad-login':
        $launchpad_login = $val;
        break;
    case 'ubuntus':
        $ubuntus = preg_split('/,/',$val,-1,PREG_SPLIT_NO_EMPTY);
        break;
    case 'pkg-modules':
        $only_modules = preg_split('/,/',$val,-1,PREG_SPLIT_NO_EMPTY);
        break;
    }
}
if ( !$launchpad_login) {
    $launchpad_login = trim(ask("What is the launchpad name/team to put packages under?"));
}

if ($keyid) {
    $keyid = '-k' . $keyid;
} else {
    $gpg_keys  = explode("\n",trim(shell_exec("gpg --list-public-keys | grep ^pub | awk '{print $2}' | awk -F / '{print $2}'")));
    if (count($gpg_keys) == 0) {
        I2CE::raiseError("Nothing to to sign with.. you need gnupg\n");
        die();
    }
    if (count($gpg_keys) > 1) {
        $keyid = '-k' . chooseMenuValue("Choose a GPG Key",$gpg_keys);
    } else {
        $keyid = false;
    }
}




if ($rc) {
    $tmp_dir_base ="/home/" . trim($whoami) . "/ihris-$rc-ppa/$launchpad_login/$ppa";
} else {
    $tmp_dir_base ="/home/" . trim($whoami) . "/ihris-ppa/$launchpad_login/$ppa";
}

if ($booleans['prompt'] && !simple_prompt("use $tmp_dir_base to store release branches and debian packaging?")) {
    while( true) {
        $tmp_dir_base = ask("What directory should I use");
        if (is_dir($tmp_dir_base)) {
            break;
        } else {
            if (simple_prompt("Create $tmp_dir_base?")) {
                exec ("mkdir -p $tmp_dir_base");
                break;
            }
        }
    }
}
$deb_src_dir = $tmp_dir_base .  '/debs';
if (!is_dir($deb_src_dir)) {
    if (!$booleans['prompt'] || simple_prompt("Create $deb_src_dir?")) {
        exec ("mkdir -p $deb_src_dir");
    } else {
        die("No place to store stuff");
    }
}

$branches_dir = $tmp_dir_base . DIRECTORY_SEPARATOR . 'branch';
if (!is_dir($branches_dir)) {
    if (!$booleans['prompt'] || simple_prompt("Create $branches_dir?")) {
        exec ("mkdir -p $branches_dir");
    } else {
        die("No place to store branches");
    }
}



$uncommitted = null;
if (count($arg_files) == 0) {
    $t_dirs = glob("*",GLOB_ONLYDIR);
} else {
    $t_dirs = $arg_files;
}
I2CE::raiseError("Searching:\n\t" . implode("\n\t",$t_dirs));



$top_dirs = array();

foreach ($t_dirs as $i=>$dir) {
    $ret = 0;
    $out = array();
    $info = @exec("bzr info $dir 2> /dev/null",$out,$ret);
    if ($ret !== 0) {
        I2CE::raiseError("Skipping $dir as it is not a bzr branch");
        unset($t_dirs[$i]);
        continue;
    }
    $ret = 0;
    $out = array();
    $info = @exec("bzr status -S $dir 2> /dev/null",$out,$ret);
    if (count($out)>0) {
        I2CE::raiseError("Warning $dir has uncommitted changes");
    }
    $dir = realpath($dir);
    $branch = basename($dir);
    $branch_dir = "$branches_dir/$branch";
    if (!is_dir($branch_dir)) {
        $cmd = "bzr branch --bind $dir $branch_dir";    
        exec($cmd);                    
    } else {
        if (!$booleans['skip-updates']) {
            //revert t
            $cmd = " bzr revert --forget-merges --no-backup -q  $branch_dir && bzr clean-tree -q --force -d $branch_dir  && bzr update $branch_dir";    
            $cmd = " bzr revert --forget-merges --no-backup -q  $branch_dir && bzr clean-tree -q --force -d $branch_dir  && bzr update $branch_dir";    
            I2CE::raiseError($cmd);
            exec($cmd);                    
        }
    }
    $top_dirs[]  = $branch_dir;
//    if (prompt("Create translations $locales in $branch_dir?",$booleans['translate'])) {
        // I2CE::raiseError("Translating for $locales in $branch_dir");
        // $cmd = "cd $branch_dir && php $i2ce_dir/tools/translate_templates.php --locales=$locales 2> /dev/null";
        // exec($cmd);
//    }
}

$t_i2ce_version = trim(exec("grep  '/version' " . escapeshellarg(realpath(dirname(__FILE__) . '/../I2CE_Configuration.xml')) . " | head -1 "));
if (!preg_match('/([0-9\.]+)/' ,$t_i2ce_version,$matches)) {
    I2CE::raiseError("Could not find a version in i2ce config file");
    die();
}
$i2ce_version = $matches[1];        

if (!$i2ce_version) {
    die("could not determine the version of I2CE\n");        
}
$i2ce_version_components = array_pad(explode(".",$i2ce_version),3,0);
$i2ce_sub_major_version = implode(".", array_slice($i2ce_version_components,0,2));
if ($rc) {
    $site_base_dir = "/var/lib/iHRIS-$rc/sites/" . $i2ce_sub_major_version;
} else {
    $site_base_dir = "/var/lib/iHRIS/sites/" . $i2ce_sub_major_version;
}


$releases = array();
$change_logs = array();
$change_files = array();
$all_changes = null;
$all_revisions = null;

foreach ($top_dirs as $dir) {
    if (! ($pcre = trim(`which pcregrep`))) {
        die("Please do: sudo apt-get install pcregrep\n");
    }
    $last_commit_revno = trim(exec( "bzr log --line -r revno:-1 $dir | $pcre -o '^[0-9\\.]+'"));
    $last_revno = $last_commit_revno;
    I2CE::raiseError("Finding release tags for $dir");
    $cmd = "bzr log --line $dir | $pcre '" . '\\{.*[0-9\\.]+-release.*\\}'  . "'";
    $out = array();
    exec($cmd, $out,$ret);
    $t_releases = array();
    foreach ($out as $line) {
        //may have multiple releases in the same revision if not files were changed
        if (preg_match('/^([0-9\\.]+):.*?\\{(.*[0-9\\.]+-release.*)\}/',$line,$matches)) {
            $revno =  $matches[1];
            //we have at least one release tag.  let's ge them all split up and sorted
            if (preg_match_all('/([0-9\\.]+)-release/',$line,$matches)) {
                if (!count($matches) == 2) {
                    continue;
                }
                $rels = $matches[1];
                if (count($rels) == 0) {
                    I2CE::raiseError("Thought I had a release, but it ran away from me ...");
                    continue;
                }
                //sort so that  the maximum release as the first entry.                
                usort($rels,'vers_compare'); 
                foreach ($rels as $rel) {
                    $t_releases[] = array($revno, $rel);
                }
                end($rels);                    
                $release = current($rels);
            }
        }
    }
    $has_release = (count($t_releases) > 0);
    $prev_release = false;
    $prev_revno = false;
    if (!$has_release) {
        I2CE::raiseError("No X.Y.Z-release tags for $dir ");
        $release = false;
        $revno = 1;
        $t_releases[] = array(1,'0.0');
        $tagged_revno = 1;
    } else {
        list($revno,$release) = $t_releases[0]; //maximum release is the first entry
        if (count($t_releases) > 1) {
            list($prev_revno,$prev_release) = $t_releases[1]; //next release is the first entry
        }
        $tagged_revno = $revno;
        $t_releases[] = array(1,'0.0');
    }

    
    
    //now we try to do some autodetection.
    $version = false;
    if (!preg_match('/name=["\'](.*?)["\']/' ,trim( exec("grep  -P 'I2CEConfiguration\s+name' $dir/*xml 2> /dev/null")),$matches)) {
        I2CE::raiseError("Could not determine packaging information for $dir");
        continue;
    }
    $module =$matches[1];


    $cmd = "grep  '/version' $dir/*xml | head -1 2> /dev/null";
    $out = array();
    $ret = false;
    @exec($cmd, $out,$ret);
    $out = implode("\n",$out);
    //$out = trim( `$cmd`);
    if (!preg_match('/([0-9\.]+)/' ,$out,$matches2)) {
        I2CE::raiseError("Could not find a version in $dir");
        continue;
    }
    $version = $matches2[1];    
    if ($last_revno != $revno) {
        if (prompt("There are revisions in " . basename($dir) ." since the last tagged release.  Ignore them?",$booleans['release-only'])) {
            $last_revno =  $revno;
        } else {
            if (prompt("Get all unreleaseed revisions for " . basename($dir) . "?",$all_revisions)) {
                array_unshift($t_releases, array($last_revno,'current'));
                list($revno,$release) = $t_releases[0]; //maximum release is the first entry         
            } else {
                $logs = 
                $options = array();
                $nextrev = $revno + 1;
                $cmd = "bzr log --line -r $nextrev.. $dir";
                foreach (explode("\n",shell_exec($cmd)) as $log) {
                    if (!preg_match('/^([0-9]+):(.*)$/',$log,$matches)) {
                        continue;
                    }
                    $options[$matches[1]] = trim($matches[2]);
                }
                $last_revno = chooseMenuIndex("Which revision do you want to package to?" , $options);
                array_unshift($t_releases, array($last_revno,'current'));
                list($revno,$release) = $t_releases[0]; //maximum release is the first entry
            }
            I2CE::raiseError("Warning: The last revision $last_revno of $dir has not been tagged as a release. Will postfix the revision count to all modified modules");  
        }
    }
    $version_components = array_pad(explode(".",$version),3,0);
    $sub_major_version = implode(".", array_slice($version_components,0,2));
    $minor_version = implode(".", array_slice($version_components,0,3));
    if ($release && ($minor_version != $release)) {
        I2CE::raiseError("Warning module version  at " . basename($dir) . " has $minor_version != " . $release . ', the tagged release version');
    }

    $releases[$module] = array(
        'dir'=>basename($dir),
        'releases'=>$t_releases, 
        'revno'=>$last_revno,
        'commit_revno'=>$last_commit_revno,
        'tagged_revno'=>$tagged_revno,
        'release'=>$release,
        'previous_release'=>$prev_release,
        'previous_revno'=>$prev_revno,
        'version'=>$version,
        'sub_major_version' => $sub_major_version,
        'minor_version' => $minor_version,
        'sites'=>glob("$dir/sites/*",GLOB_ONLYDIR)
        );
}



$revert_nontrans = null;
$reverts_to_release = array();
foreach ($releases as $module=>$data) {
    $top_dir =  realpath($branches_dir .'/'. $data['dir']);
    if (!$rc && ($data['commit_revno'] != $data['revno']) && prompt("Would you like to revert $module to revision {$data['revno']}, excepting translations?",$revert_nontrans)) {
        I2CE::raiseError("Branch $module has unreleased revisions and this is not a release candidate packge.  Reverting to " . $data['revno'] . " excepting translations");
        //not a release candidate. and we do not want all commits that 
        //do not need to revert anything.
        //need to revert the branches (excluding translations/tempaltes directory)  to the last wanted commit
        $reverts_to_release[] = $module;
        $reverts = glob("$top_dir/*");
        foreach ($reverts as $i=>&$r) {
            if (substr($r,-13) == '/translations') {
                unset($reverts[$i]);
            } else {
                $r = substr($r,strlen($top_dir) +1);
            }
        }
        unset($r);
        $cmd = "cd $top_dir && bzr  revert -r " . $data['revno']  . ' ' . implode(" ", $reverts);
        I2CE::raiseError($cmd);
        exec($cmd);
    }
    

    //now we handle translations.
    $cmd = "cd $top_dir && php $i2ce_dir/tools/translate_templates.php --overwrite-all=T --locales=$locales 2> /dev/null";
    I2CE::raiseError("Translating for $locales in $top_dir:\n$cmd");
    exec($cmd);
    I2CE::raiseError("Done translating $top_dir");
}


$site_dirs =array();
foreach ($releases as $module=>$data) {
    $site_dirs= array_merge($site_dirs,$data['sites']);
}
$search_dirs = array_merge($top_dirs,$site_dirs);

$msg = "Working with the following top-level:\n\t" . implode("\n\t",$top_dirs) . "\n";
if (count($site_dirs) > 0)  {
    $msg .="and site directories:\n\t" . implode("\n\t",$site_dirs) ."\n";
}
I2CE::raiseError($msg);




//get  all versioned  files w/ release/revno info by top level/paxkaging module

foreach ($releases as $module =>$data) {
    $dir = $data['dir'];
    $revno = $data['revno'];
    $out =array();
    I2CE::raiseError("Reading in revisions up to (rev $revno) in $dir");
    $cmd = "bzr log --long -v -n1 -r ..$revno $branches_dir/$dir";
    $ret = 0;
    exec($cmd,$out,$ret);
    I2CE::raiseError("Processsing revisions up to (rev $revno) in $dir for change log");
    
    $change_logs[$module] = array();
    $in_rev = false;
//------------------------------------------------------------
//revno: 2297
//committer: Carl Leitner <litlfred@ibiblio.org>
//branch nick: 4.0-dev
//timestamp: Tue 2010-12-14 10:26:31 -0500
//message:
//   translate templates will try to solidify source and translated text in case the sites .po files have not been updated
//removed:
//   some/file/name
//modified:
//   tools/translate_templates.php
    $files = array();
    $message = '';
    $rev_no = false;
    $timestamp = false;
    $committer = false;
    $in_mod = false;
    $in_rnm = false;
    $bugs = '';
    $change_files[$module]= array();    
    $release = 'current'; 
    $has_merge =false;
    foreach ($out as $line) {
        $line = rtrim($line);
        if (substr($line,0,10)  == '----------') {
            //new revision
            if ($rev_no) {
                if ($has_merge) {
                    $merge_cmd = "cd  $branches_dir/$dir && bzr log --line -n2 -r $rev_no";
                    foreach (explode("\n",shell_exec($merge_cmd)) as $log) {
                        if (!preg_match('/^\s+([0-9\\.]+):(.*)$/',$log,$merge_matches)) {
                            //skip the top-level comment as it is already in there
                            continue;
                        }
                        $message .=  "  * [m]" . implode("\n    ",explode("\n",wordwrap(trim($merge_matches[2]))))  . "\n";
                    }
                }
                $change_logs[$module][$rev_no] = array('committer'=>$committer,'message'=>rtrim($message) . $bugs,'timestamp'=>$timestamp);
                foreach ($files as $file) {
                    if (!array_key_exists($file,$change_files[$module])) {
                        $change_files[$module ][ $file] = array();
                    }
                    if (!array_key_exists($release,$change_files[$module][$file])) {
                        $change_files[$module ][ $file][$release] = array();
                    }
                    $change_files[$module][$file][$release][] = $rev_no;
                }
                $has_merge = false;
            }
            $files = array();
            $message = '';
            $bugs = '';
            $rev_no  =false;
            $timestamp = false;
            $committer = false;
            $in_mod = false;
            $in_rnm = false;
        } else if (preg_match('/^revno:\s+([0-9\.]+)\s+\\[merge\\]/',$line,$matches)) {
            //get in merge comments as well
            $rev_no = $matches[1];
            $in_msg = false;
            $in_mod = false;
            $in_rnm = false;
            $has_merge = true;
        } else if (preg_match('/^revno:\s+([0-9\.]+)/',$line,$matches)) {
            $rev_no = $matches[1];
            $in_msg = false;
            $in_mod = false;
            $in_rnm = false;
            $has_merge = false;
        } else if (preg_match('/^committer:\s+(.*?)\s*$/',$line,$matches)) {
            $committer = $matches[1];
            if (preg_match('/litlfred/', $committer)) {
                $committer = "Carl Leitner <litlfred@ibiblio.org>";
            }   else if (preg_match('/lduncan/', $committer)) {
                $committer = "Luke Duncan <lduncan@intrahealth.org>";
            }
            $in_msg = false;
            $in_mod = false;
            $in_rnm = false;
        } else if (preg_match('/^timestamp:\s+(.*?)\s*$/',$line,$matches)) {
            //input: Mon 2010-06-07 13:33:42 -0400
            //output: Mon, 07 June 2010 13:33:42 -0400
            // day-of-week, dd month yyyy hh:mm:ss +zzzz 
            $timestamp = $matches[1];
            $in_msg = false;
            $in_mod = false;
            $in_rnm = false;
        } else if (preg_match("/^branch nick:/",$line)) {
            $in_msg = false;
            $in_mod = false;
            $in_rnm = false;
            //ignore
        } else if (substr($line,0,13) == 'fixes bug(s):'){
            $in_msg = false;
            $in_mod = false;
            $in_rnm = false;
            $bugs .= "\n *   Fixes Bugs: " . trim(substr($line,14)) . "\n";
        } else if (preg_match("/^tags:/",$line)) {
            //one revision by be tagged for multiple releases.  if this is the case, we want the earliest one.
            if (preg_match_all('/([0-9\\.]+)-release/',$line,$matches)) {
                if (!count($matches) == 2) {
                    continue;
                }
                $rels = $matches[1];
                if (count($rels) > 0) {
                    //sort so that  the maximum release as the first entry.                
                    usort($rels,'vers_compare'); 
                    end($rels);                    
                    $release = current($rels);
                }
            }
            $in_msg = false;
            $in_mod = false;
            $in_rnm = false;
            //ignore
        } else if (preg_match("/^message:/",$line)) {
            $in_msg = true;
            $in_mod = false;
            $in_rnm = false;
        } else if (preg_match("/^removed:/",$line) || preg_match("/^added:/",$line) || preg_match("/^modified:/",$line) || preg_match("/^kind changed:/",$line)) {
            $in_msg = false;
            $in_mod = true;
            $in_rnm = false;
        } else if (preg_match("/^renamed:/",$line)) {
            $in_msg = false;
            $in_mod = false;
            $in_rem = false;
            $in_add = false;
            $in_rnm = true;
        } else if (preg_match("/^[a-zA-Z][a-zA-Z\s]+:/",$line)) {
            $in_msg = true;
            $in_mod = false;
            $in_rem = false;
            $in_add = false;
            $in_rnm = false;
            I2CE::raiseError(   "Dont knwo what to do with:\n[[$line]]\n");
        } else if ($in_msg) {
            $line = implode("\n    ",explode("\n",wordwrap(trim($line))));
            if ($message) {
                $message .= "    " . $line ."\n";
            } else {
                $message  = "  * " . $line ."\n";
            }
        } else if ($in_mod) {
            if ($line) {
                $files[] = trim($line);
            }
        } else if ($in_rnm) {
            list($source,$dest) = explode("=>",$line);
            if ( ($source = trim($source)) && ($dest = trim($dest))) {
                $files[] = $dest;
                $files[] = $source;
            }
        } else {
            die ("Don't know what to do with (no-state):\n[[$line]]\n");
        }
        
        
    }
    if ($rev_no) {
        //get the (first = last)  revision
        $change_logs[$module][$rev_no] = array('committer'=>$committer,'message'=>rtrim($message)  . $bugs,'timestamp'=>$timestamp);
        foreach ($files as $file) {
            if (!array_key_exists($file,$change_files[$module])) {
                $change_files[$module ][ $file] = array();
            }
            if (!array_key_exists($release,$change_files[$module][$file])) {
                $change_files[$module ][ $file][$release] = array();
            }
            $change_files[$module][$file][$release][] = $rev_no;
        }

    }
}




chdir($branches_dir);
getAvailableModules();




//NOW make sure we know in which branch each module is in, as well as its directory and version (some modules may not be i2ce modules but packaging modules)
$mod_dirs =array();
$branch_modules = array();
$config_dirs = array();
$versions = array();
foreach ($found_modules as $module=>$top_module) {
    if (!array_key_exists($top_module,$mod_dirs)) {
        $mod_dirs[$top_module] = array();
    }


    $version  = false;
    if (!$storage->setIfIsSet($version,"/config/data/$module/version")) {
        I2CE::raiseError("Cannot get version for $module.  Skipping");
        continue;
    }
    $config_file = false;
    if ( ! $storage->setIfIsSet($config_file,"/config/data/$module/file")) {
        I2CE::raiseError( "No config file for $module -- Skipping");
        continue;
    }
    $config_dir = rtrim(dirname($config_file),'/');
    $mod_dirs[$top_module][$config_dir] = $module;
    $config_dirs[$module] = $config_dir;
    $versions[$module] = $version;


    
    //now get the top level module  in the branch
    if (array_key_exists($top_module,$releases)) {
        $b_module = $top_module;
    } else {
        //this is a site specific module or soemthing along these lines.   need to determin the containing directory.
        $b_module = false;
        foreach ($releases as $m=>$data) {
            $b_dir = rtrim($branches_dir,'/') . '/' . $data['dir'] ;
            if (strpos($config_dir,$b_dir) === 0) {
                $b_module = $m;
                break;
            }
        }
        if (!$b_module) {
            I2CE::raiseError("Could not containing branch  module for $module ($top_module)");
            continue;
        }
    }
    $branch_modules[$module] = $b_module;
}


$ignore_filenames = array('/^\\.bzr/','/^\\.htaccess/');
$ignore_filepaths = array(
    '/^pages\\/config\\.values\\.php/',    '/\\/pages\\/config\\.values\\.php/'
    ); //filepath is relative to top dir
$nocopy_filenames = array();
$nocopy_filepaths = array(
    '/^packaging$/','/\\/packaging$/','/^packaging\\//','/\\/packaging\\//'
    );

    
$versioned_files = array();
$no_copy_files = array();

I2CE::raiseError("Getting all versioned files by module");
$translation_files = array();
foreach ($found_modules as $module=>$top_module) {
//    if (count($only_modules) > 0 && !in_array($module,$only_modules) && $module != $top_module) {
    if (!array_key_exists($top_module,$versioned_files)) {
        $versioned_files[$top_module] = array();
    }
    if (!array_key_exists(    $branch_modules[$module] ,$translation_files)) {
        $translation_files[    $branch_modules[$module] ] = array();
    }
    if (!array_key_exists($top_module,$no_copy_files)) {
        $no_copy_files[$top_module] = array();
    }
    $versioned_files[$top_module][$module] = array();    
    $no_copy_files[$top_module][$module] = array();


    if (count($only_modules) > 0 && !in_array($module,$only_modules) ) {
        //if we are only building a few modules, and this module is not in our list, skip out        
        continue;
    }
    if (!array_key_exists($module,$branch_modules)) {
        I2CE::raiseError("no branch module for module $module");
        continue;
    }
    if (!array_key_exists($module,$config_dirs)) {
        I2CE::raiseError("no config dir for module $module");
        continue;
    }
    if (!array_key_exists($top_module,$config_dirs))  {
        I2CE::raiseError("no config dir for top module  $top_module");
        continue;
    }
    $config_dir = $config_dirs[$module];
    $t_config_dir = $config_dirs[$top_module];

    $v = array();


    $dirs = array();
    //$ignore = array('MODULES','TEMPLATES','CONFIGS');
    $ignore = array('MODULES');
    foreach ($ignore as $type) {
        $dirs[$type] = array();
        $storage->setIfIsSet($dirs[$type],"/config/data/$module/paths/" . $type,true);
        foreach ($dirs[$type]  as $i=>&$s_d) {
            if ($s_d[0] != '/') {
                $s_d = realpath($config_dir . '/' . $s_d);
            } else {
                $s_d = realpath($s_d);
            } 
            if (!is_dir($s_d) || strpos($s_d,$config_dir) !== 0) { //check to see ensure is a subdir
                unset($dirs[$type][$i]);
                continue;
            }
        }
        unset($s_d);
    }


    $v = array();
    $nc_files = array();
    @exec("bzr ls -R -V $config_dir",$v);

    $t_dir_len = strlen($t_config_dir) + 1;
    foreach ($v as $i=>&$f) {
        $f = trim($f);
        if (!$f) {
            unset($v[$i]);
            continue;
        }
        $filepath = substr(trim($f),$t_dir_len);
        $filename = basename($f);

        foreach ($ignore_filepaths as $regexp) {
            if (preg_match($regexp,$filepath)) {
                unset($v[$i]);
                continue 2; //skip to next file
            }
        }
        foreach ($ignore_filenames as $regexp) {
            if (preg_match($regexp,$filename)) {
                unset($v[$i]);
                continue 2;  //skip to next file
            }
        }
        $no_copy = false;
        foreach ($nocopy_filepaths as $regexp) {
            if (preg_match($regexp,$filepath)) {
                $no_copy =true;
                break;
            }
        }
        foreach ($nocopy_filenames as $regexp) {
            if (preg_match($regexp,$filename)) {
                $no_copy =true;
                break;
            }
        }

        // //first we ignore things that are in an ingored  subdirectory
        // foreach ($ignore as $type) { 
        //     foreach ($dirs[$type] as $s_d) {
        //         if (strpos($f,$s_d) === 0) {
        //             unset($v[$i]);
        //             continue 3;
        //         }
        //     }
        // }

        //now we check that it not within another module's config_dir 
        foreach ($config_dirs as $m=>$m_config_dir) {
            if ($m == $module) {
                continue;  //skip to next module
            }
            
            if (strpos($config_dir,$m_config_dir)===0) { //haystack, needle
                //we don't want to check against any modules $m which is a parent module (i.e. $m_config_dir is a subtrs of $config_di)
                //this modules config directory is a parent of ours
                continue;
            }
            //check to see if this con
            if (strpos($f,$m_config_dir)===0) { //haystack, needle
            
                unset($v[$i]);
                continue 2;  //skip to next file
            }
        }
        //now we check that it is not a  mod glob dir
        if (is_dir($f)) {
            $t_f = rtrim($f,'/');
            foreach ($site_dirs as $s_dir) {
                if ($t_f == rtrim($s_dir,'/')) {
                    unset($v[$i]);
                    continue 2; //sjip to next file
                }
            }
        }


        //now strip out begining of filename so we are only relative to the top level package module (e.g. a site module or a i2ce,irhis-common etc)
        $f = $filepath;
        if ($no_copy) {
            $nc_files[] = $filepath;
        }

        

        if ((strpos($f,'translations/templates/') === 0) && substr($f,-4) == '.pot') {
            //it is a pot file we need to keep track of
            $translation_files[$branch_modules[$module]][] = $f;
        }
        
        //if $top_module == $module we ignore the translations and a few other anothing directories
        //foreach (array('translations','tools','t','tests') as $ig) {
        foreach (array('translations','t','tests','maintainer_tools') as $ig) {
            if ($f == $ig || strpos($f, "$ig/") === 0) {
                unset($v[$i]);
                continue 2; //skip to next file
            }
        }
        
    }
    unset($f);
    $find_templates = array();
    $find_configs = array();
    @exec("find $config_dir/templates/ 2> /dev/null ",$find_templates);
    @exec("find $config_dir/configs/ 2> /dev/null ",$find_configs);
    if (!is_array($find_templates)) {
        $find_templates = array();
    }
    if (!is_array($find_configs)) {
        $find_configs = array();
    }
    foreach ($find_configs as &$f) {
        $f = substr(trim($f),$t_dir_len);
    }
    unset($f);
    foreach ($find_templates as &$f) {
        $f = substr(trim($f),$t_dir_len);
    }
    unset($f);
    $v =array_unique(array_merge($find_templates,$find_configs,$v));
    //for safety... now make  sure there are not files here that are in a local subdirectory
    foreach ($v as $i=>$f) {
        if (strpos($f,'/local/') !== false || substr($f,0,6)== 'local/' || substr($f,-6) == '/local') {
            unset($v[$i]);
        }
    }
    $versioned_files[$top_module][$module] = $v;
    $no_copy_files[$top_module][$module] = $nc_files;
}


function launchpadTemplateName($str) {
    $func = create_function('$c', 'return strtolower($c[1]) . "_" . strtolower($c[2]);');
    return strtr(strtolower(preg_replace_callback('/([a-z])([A-Z])/', $func, $str)),'_','-');
}

///////////////////END GETTING VERSIOEND




I2CE::raiseError("Got all versioned files by module");

I2CE::raiseError("Producing changelog for modules");
$new_changes  = array();
$changed_modules = array();
foreach ($found_modules as $module=>$top_module) {
    if (count($only_modules) > 0 && !in_array($module,$only_modules)) {
        //if we are only building a few modules, and this module is not in our list, skip out
        continue;
    }
    if (!array_key_exists($module,$versioned_files[$top_module])) {
        I2CE::raiseError("Module $module has no versioned files ($top_module) registered");
        continue;
    }
    if ( count($versioned_files[$top_module][$module]) == 0) {
        I2CE::raiseError("Module $module has no versioned files ($top_module)");
        continue;
    }
    if (!array_key_exists($module,$branch_modules)) {
        I2CE::raiseError("Dont knwo which branch $module is in");
        continue;
    }
    $b_module = $branch_modules[$module];
    if (!array_key_exists($b_module,$change_logs) || count($change_logs[$b_module]) == 0) {
        I2CE::raiseError("branch module $b_module of module $module has no change logs");
        continue;
    }
    if (!array_key_exists($module,$versions)) {
        continue;
    }
    if (!array_key_exists($module,$config_dirs)) {
        continue;
    }
    if (!array_key_exists($top_module,$config_dirs)) {
        continue;
    }
    if (!array_key_exists($b_module,$config_dirs)) {
        continue;
    }

    $version = $versions[$module];

    $config_dir = $config_dirs[$module];
    $t_config_dir = $config_dirs[$top_module];
    $b_config_dir = $config_dirs[$b_module];


    $launchpadName = launchpadTemplateName($module);
    $potFile = "translations/templates/" . $launchpadName . '/' .$launchpadName . '.pot';
    $hasPot =in_array($potFile,$translation_files[$b_module]);
    


    $changelog = '';
    $revs_by_release = array();
    $b_module == $branch_modules[$module];
    $prefix  ='';
    if ($b_module !== $top_module) {
        //it is a site module and there fore the prefix needs to change for the file
        $prefix = substr($t_config_dir,strlen($b_config_dir)+1) .'/';
    }
    foreach ($releases[$b_module]['releases'] as $rev_release) {
        list($rev,$release) = $rev_release;
        $revs = array();
        foreach ($versioned_files[$top_module][$module] as $file) {
            if (!array_key_exists($prefix.$file,$change_files[$b_module])) {
                continue;
            }
            if (!array_key_exists($release,$change_files[$b_module][$prefix.$file])) {
                continue;
            }
            $revs = array_unique(array_merge($revs,$change_files[$b_module][$prefix.$file][$release]));
        }
        if (count($revs) == 0) {
            continue;
        }
        $revs_by_release[$release] = $revs;
    }
    $max_trans_rev = 1;
    $newly_changed = false;
    if ($hasPot) {
        I2CE::raiseError("$module has translations --- adding in versioning info for $locales") ;
        foreach (explode(",",$locales) as $locale) {
            $poFile = "translations/templates/" . $launchpadName . '/' .$locale . '.po';
            if (!array_key_exists($poFile,$change_files[$b_module])) {
                continue;
            }
            foreach ($change_files[$b_module][$poFile] as $trans_release=>$trans_revs) {
                //I2CE::raiseError("Adding translation revision on release ($trans_release) fo $poFile:"  . implode(',',$trans_revs));
                if ($trans_release == 'current') {
                    $newly_changed = true;
                }
                $max_trans_rev = max($max_trans_rev, max($trans_revs));
                if (!array_key_exists($trans_release,$revs_by_release)) {
                    $revs_by_release[$trans_release] = array();
                }
                $revs_by_release[$trans_release] = array_unique(array_merge($revs_by_release[$trans_release],$trans_revs));
            }
        }
    }

    foreach ($revs_by_release as $release => &$revs) {
        usort($revs,'vers_compare');
    }
    unset($revs);
    uksort($revs_by_release,'current_vers_compare');



    if (count($revs_by_release) == 0) {
        I2CE::raiseError("No revisions for $module");
        continue;
    }
    $changelog = '';
    $first = true;

    $max_rev = 1;
    $pkg_version = false;
    foreach ($revs_by_release as $release=>$revs) {
        //$change_logs[$top_mod][$rev_no] = array('committer'=>$committer,'rnm_files'=>$rnm_files,'mod_files' => $mod_files,'rem_files'=>$rem_files,'add_files'=>$add_files,'message'=>$message . $bugs,'timestamp'=>$timestamp);
        $t_changelog = '';
        $timestamp = false;
        foreach ($revs as $rev) {
            $max_rev = max($max_rev,$rev);
            if (!array_key_exists($rev,$change_logs[$b_module])) {
                continue;
            }
            if ($timestamp === false) {
                $timestamp = strftime("%a, %d %B %Y %H:%M:%S %z",strtotime($change_logs[$b_module][$rev]['timestamp']));
            }
            $t_changelog .=  
                '  [' . trim($change_logs[$b_module][$rev]['committer']) ."]\n" . 
                rtrim(preg_replace("/\n\n+/","\n",$change_logs[$b_module][$rev]['message'] )). "\n    " . trim($change_logs[$b_module][$rev]['timestamp']) . "\n";
        }
        if (!$t_changelog) {
            $first = false;
            continue;
        }
        if ($release == 'current') {
            //there are changes since the last release
            $diff = max($max_trans_rev, (int)$max_rev)  -  ((int)$releases[$b_module]['tagged_revno']);
            $release_version = $version .'+'. $diff;
        } else {
            //there are no changes since
            $release_version = $version;  
        }
        if ($first) {
            $first = false;
            $pkg_version = $release_version;
            if ($releases[$b_module]['release']== 'current') {  
                //this is an update package since the last release. we check this rev. against the revno of the last relase of the brachs
                $newly_changed = $newly_changed ||  ( (int)$max_rev > (int) $releases[$b_module]['tagged_revno']);
            } else  {
                //this is a package on a release. 
                if ($releases[$b_module]['previous_revno']) {
                    //  we need to see if the module has change since the previous release
                    $newly_changed  = $newly_changed ||  ( (int) $max_rev >  (int)$releases[$b_module]['previous_revno']);
                } else {
                    //there was no previous release.  this is the first release. we need to consider that this is newly changed?
                    $newly_changed = true;
                }

                //this is package on a release. we check to see if this module agrees with the release tag
                // explode('.',$release_version);
                // array_pad(explode('.',$release_version) ,3,'0');
                // array_slice(array_pad(explode('.',$release_version) ,3,'0'),0,3);
                // $short_release_version = implode(".",array_slice(array_pad(explode('.',$release_version) ,3,'0'),0,3));
                // if ($short_release_version == $releases[$b_module]['release']) {  
                //     I2CE::raiseError("$module is newly changed on release ". $releases[$b_module]['release']);
                //     $newly_changed = true;
                // }
            }

        }
        $change_header = makePackageName($module) ." (<<<UBUNTURELEASE>>>) <<<UBUNTUSERIES>>>; urgency=low\n\n";
        $changelog .= $change_header . $t_changelog . "\n --  Carl Leitner <litlfred@ibiblio.org>  $timestamp\n\n";
    }
    if  (!$changelog) {
        I2CE::raiseError("Cannot produce changelog for $module");
        continue;
    }    
    if (!array_key_exists($top_module,$changed_modules)) {
        $changed_modules[$top_module] = array();
    }
    if ($newly_changed) {
        $new_changes[] = $module;
    }
    $changed_modules[$top_module][$module]=array('pkg_version'=>$pkg_version,'changelog'=>$changelog, 'version'=>$version,'newly_changed'=>$newly_changed,'top_module'=>$top_module );
}


if (count($new_changes) > 0) {
    I2CE::raiseError("Newly changed modules since last release:\n\t" . implode(",",$new_changes));
}




//in case it is not present, attempt to create it
if ($rc) {
    createPPA($launchpad_login,$ppa,'iHRIS ' . $rc, 'iHRIS ' . $rc . ' Repository');
} else {
    createPPA($launchpad_login,$ppa,'iHRIS', 'iHRIS Repository');
}
$existing_versions = getExistingPublishedVersions($launchpad_login,$ppa);
if (!is_array($existing_versions)) {
    $existing_versions = array();
}

        
I2CE::raiseError("Producing deb sources");
$deb_pkgs = array();
$clear_old = null;


function incrementDecimal($ver) {
    list($base,$dec) = array_pad(explode('.',$ver),2,0);
    $dec++;
    return $base . '.' . $dec;
}


$do_upload = null;

$dont_rebuild = null;
foreach ($ubuntus as $ubuntu) {
    $deb_pkgs[$ubuntu] = array();
    $ubuntu_version =  '~' . $ubuntu;
                 
    foreach ($changed_modules as $top_module=>$changed_mods) {
        foreach ($changed_mods as $module=>$data) {
            $version  = $data['version'];

            if (!array_key_exists($module,$branch_modules)) {
                I2CE::raiseError("Dont know which branch $module is in");
                continue;
            }
            $b_module = $branch_modules[$module];
            if (!array_key_exists($module,$config_dirs)) {
                continue;
            }
            if (!array_key_exists($top_module,$config_dirs)) {
                continue;
            }
            if (!array_key_exists($b_module,$config_dirs)) {
                continue;
            }
            if (!$data['newly_changed']) {
                if (prompt("Skip $ubuntu .deb generation for module which has not been chagned since the last release ($module)?",$booleans['only-new'])) {
                    continue;
                }
            }


            $config_dir = $config_dirs[$module];
            $t_config_dir = $config_dirs[$top_module];
            $b_config_dir = $config_dirs[$b_module];

            $pkg_name = makePackageName($module);

            if (!array_key_exists($pkg_name,$existing_versions)) {
                $existing_versions[$pkg_name] = array();
            }
            list($vers_base,$vers_rev) = array_pad(explode('+',$data['pkg_version'],2),2,'');
            if (!$vers_rev) {
                $vers_rev = 0;
            }
            $matched_rev = 0;
            $has_bad_rev_version = false;
            $has_bad_version = false;
            foreach ($existing_versions[$pkg_name] as $existing_ver) {
                list($exis_vers_base,$exis_vers_rem) = array_pad(explode('+',$existing_ver,2),2,'');
                list($exis_vers_rev,$exis_vers_rem) = array_pad(explode('~',$exis_vers_rem,2),2,'');
                list($exis_vers_ubu,$exis_vers_deb) = array_pad(explode('-',$exis_vers_rem,2),2,'');
                if (!$exis_vers_rev) {
                    $exis_vers_rev = 0;
                }
                if ($exis_vers_ubu != $ubuntu) {
                    continue;
                }
                if (vers_compare($exis_vers_base,$vers_base) == -1) {
                    $has_bad_version = true;
                    I2CE::raiseError("Wanrning there is an existing published version ($exis_vers_base) for $ubuntu of $module which is bigger than what you are currently trying to package ($ver_base).  Luanchpad will send you an error email");
                    break;
                } else   if (vers_compare($exis_vers_base,$vers_base) == 0) { 
                    //this published versions  matches.  let see if there is a revision part to the version version.
                    if ($exis_vers_rev > $vers_rev) {
                        $has_bad_rev_version = true;                    
                        //the published revision is bigger than the revision we are trying.  we may need to fix things
                        $matched_rev = max ($matched_rev,$exis_vers_rev);
                    } else {
                        //if $exis_ver_rev == $vers_rev, it could just be a repackging. in any case it will be handled below with the debian version packaging -1,-2,etc..
                        //if $exis_rev_rev < $vers_rev we are fine.
                    }
                } else { //f (vers_compare($exis_vers_base,$vers_base) == 1
                    //this published version has a version which is slictly less than what we are trying to do now.
                    continue;
                }
                
            }
            if ($has_bad_version ) {
                //doesn't really mater.  launchpad is going to complain when we upload this and there is something wrong that needs to be fixed in the module's version
                $source_version = $data['pkg_version'];
            } else if ($has_bad_rev_version) {
                $source_version = $vers_base. '+' . incrementDecimal($matched_rev) ;
            } else {
                $source_version = $data['pkg_version'];
            }
            $source_version  .= $ubuntu_version;

            $debian_version = '';            
            if (in_array($source_version, $existing_versions[$pkg_name])) {
                if (prompt("The package $pkg_name already has the source $source_version uploaded to ppa:$launchpad_login/$ppa.  Skip rebuilding?",$dont_rebuild)) {
                    continue;
                }
                $debian_version_counter = 1;
                $debian_version = '-' . $debian_version_counter;
                $t_source_version =  $source_version . $debian_version;
                while (in_array($t_source_version,$existing_versions[$pkg_name])) {
                    $debian_version_counter++;
                    $debian_version = '-' . $debian_version_counter;
                    $t_source_version = $source_version  . $debian_version;
                }
                $source_version = $t_source_version;

            }
            $package = $pkg_name . '-' .$source_version;
            I2CE::raiseError("Building $module source package: $package");
            //$pkg_dir = $pkg_name . '-' .$data['pkg_version'] ;
            $pkg_dir = $package;
            $d_base_dir = $deb_src_dir . '/' . $ubuntu . '/' .             $pkg_dir;
            if (is_dir($d_base_dir)) {
                if (prompt("The source directory $d_base_dir already exists.  Should I delete the contents?",$clear_old)) {
                    exec("rm -fr $d_base_dir/*");
                }
            }

        
            $deb_options_dir = $config_dir . '/packaging/DEBIAN/' . $ubuntu;
            $deb_sources_dir = $deb_options_dir . '/source/';
            $deb_options_file = $deb_options_dir . '/config.php';
            $recommends = array();
            $conflicts = array();
            $breaks = array();
            $depends = array();
            $scripts = array();
            $conf_files =array();
            $database = false;
            if (is_readable($deb_options_file)) {
                I2CE::raiseError("Loading $deb_options_file for $module");
                include($deb_options_file);
            }

            $depends[] = 'php5 (>= 5.2.6)';
        

            $prefix  ='';
            if ($b_module != $top_module) {
                //it is a site (sub-)module and there fore the prefix needs to change for the file
                $prefix = substr($t_config_dir,strlen($b_config_dir) +1) .'/';
            }
        

     
            $deb_pkgs[$ubuntu][$module] = $pkg_dir; 
            $d_dir = $d_base_dir . '/debian';
            exec("mkdir -p $d_dir");
            $f_dir = $d_dir .'/source'; 
            exec("mkdir -p $f_dir");            
            // $f_file = $f_dir .'/format'; 
            // $format = '1.0';
            // file_put_contents($f_file,$format);

            $c_file = $d_dir .'/changelog'; 
            file_put_contents($c_file,str_replace('<<<UBUNTURELEASE>>>', $source_version , str_replace('<<<UBUNTUSERIES>>>',$ubuntu,$data['changelog'])));
            



            $c_file = $d_base_dir .'/debian/copyright';
            $copyright = "This package was debianized by Carl Leitner <litlfred@ibiblio.org>\n" . gmdate(DATE_RFC822) . "\n\n"
                ."It was downloaded from: https://code.launchpad.net/" . $branch_modules[$module]. " \n\n"
                ."Upstream Author(s): Carl Leitner <litlfred@ibiblio.org>, Luke Duncan <lduncan@intrahealth.org>\n\n"
                ."Copyright:\n\tCopyright (C) 2007,2008,2009,2010 by Intrahealth International, Inc. \n\n"
                ."License:\n\tGPL-3\n"
                ."This program is free software; you can redistribute it and/or modify\n"
                ."it under the terms of the GNU General Public License as published by\n"
                ."the Free Software Foundation; either version 3 of the License, or\n"
                ."(at your option) any later version.\n"
                ."This program is distributed in the hope that it will be useful,\n"
                ."but WITHOUT ANY WARRANTY; without even the implied warranty of\n"
                ."MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the\n"
                ."GNU General Public License for more details.\n"
                ."You should have received a copy of the GNU General Public License along\n"
                ."with this program; if not, write to the Free Software Foundation, Inc.,\n"
                ."51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.\n"
                ."On Debian systems, the full text of the GNU General Public\n"
                ."License version 3 can be found in the file\n"
                ."`/usr/share/common-licenses/GPL-3'\n\n"
                ."Packaging:\n\tCopyright (C) 2007,2008,2009,2010 by Intrahealth International, Inc.\n\n";
            file_put_contents($c_file,$copyright);
        
            $storage->setIfIsSet($desc,"/config/data/$module/description");
            if ($rc) {
                $desc = "iHRIS $rc Module $module\n "  . implode("\n " , explode("\n",wordwrap(preg_replace("/\n\n+/","\n .\n" ,$desc))));
            } else {
                $desc = "iHRIS Module $module\n "  . implode("\n " , explode("\n",wordwrap(preg_replace("/\n\n+/","\n .\n" ,$desc))));
            }


            $req_list = array();
            $storage->setIfIsSet($req_list,"/config/data/$module/requirement",true);
            foreach ($req_list as $m=>$reqs) {
                $dep = makePackageName($m);
                foreach ($reqs as $req) {
                    switch (strtolower($req['operator'])) {
                    case 'lessthan':
                        $depends[] = "$dep (<< " . $req['version'] . $ubuntu_version . ')';
                        break;
                    case 'greaterthan':
                        $depends[] = "$dep (>> " . $req['version'] . $ubuntu_version . ')';
                        break;

                    case 'atleast':
                        $depends[] = "$dep (>= " . $req['version'] . $ubuntu_version . ')';
                        break;

                    case 'atmost':
                        $depends[] = "$dep (<= " . $req['version'] . $ubuntu_version . ')';
                        break;
                    default:
                        break;
                    }
                }
            }


            $p_dir  = dirname(dirname($config_dir));
            while ($p_dir && array_key_exists($p_dir,$mod_dirs[$top_module])) {
                //we want to add any  parent modules as recommends
                $p_mod = $mod_dirs[$top_module][$p_dir];
                $p_dir  = dirname(dirname($p_dir));
                $dependencies =   $configurator->getDependsList($p_mod);       
                if (in_array($module,$dependencies)) {
                    //we don't this module to depend on any module that is dependent on it.
                    continue;
                }
                $recommends[] = makePackageName($p_mod);
            }
            //to be extra sure, add in all sub-modules as recommends
            foreach ($mod_dirs[$top_module] as $s_dir=>$s_mod) {
                if (strpos($s_dir,$config_dir) !== 0) {
                    //$s_dir is not a sub-dirctoru of this modules condfig dir
                    continue;
                }
                $dependencies =   $configurator->getDependsList($s_mod);       
                if (in_array($module,$dependencies)) {
                    //we don't this module to depend on any module that is dependent on it.
                    continue;
                }
                $recommends[] = makePackageName($s_mod);
            }
            I2CE::raiseError("Module $module auto-recommends on " . implode(",",$recommends));
            $req_list = array();
            $storage->setIfIsSet($req_list,"/config/data/$module/enable",true);
            foreach ($req_list as $m) {
                $recommends[] = makePackageName($m);
            }


        
        
        
            $files = $versioned_files[$top_module][$module];        
            $src_dir = "$branches_dir/{$releases[$b_module]['dir']}/$prefix";
            $is_site_module =  in_array("pages/index.php",$files);
            $is_site_top_module =  array_key_exists($top_module,$versioned_files[$top_module]) && in_array("pages/index.php",$versioned_files[$top_module][$top_module]); //the top module of this module is a site.        

            $is_within_site = ($top_module != $b_module) || $is_site_module || $is_site_top_module;

            if (!$is_within_site) {
                if ($rc) {
                    $tgt_dir =  $d_base_dir . "/usr/lib/iHRIS-$rc/lib/" . $releases[$b_module]['sub_major_version'] . "/" . $b_module ;  //defaults to  /usr/lib/iHRIS-RC1/lib/4.X/$b_module
                } else {
                    $tgt_dir =  $d_base_dir . "/usr/lib/iHRIS/lib/" . $releases[$b_module]['sub_major_version'] . "/" . $b_module ;  //defaults to  /usr/lib/iHRIS/lib/4.X/$b_module
                }
                //there are some files that might live in the site dir e.g. if this file is in the sites direcotory of the top module e..g a commong xml included config file like is done for CSSC zonal deployment
                foreach ($files as $file) {
                    if (substr($file,0,6) == 'sites/' && strlen($file) > 6) {
                        exec("mkdir -p $d_base_dir$site_base_dir");
                        break;
                    }
                }
            } else {
                I2CE::raiseError("$module is within site for $top_module"); //defaults to /var/lib/iHRIS/sites
                $tgt_dir = $d_base_dir .  $site_base_dir . '/' . $top_module;
                $vared = true;
            }

            exec("mkdir -p $tgt_dir");


            if ($is_site_module) {
                $htaccess_file =  "pages/htaccess.TEMPLATE";
            } else {
                $htaccess_file = false;
            }
            $has_rewrite = false;
            $apache_conf_file = false;
            foreach  ($files as $file) {            
                if (in_array($file,$no_copy_files[$top_module][$module])) {
                    continue;
                }

                if (substr($file,0,6) == 'sites/') {
                    if  ( strlen($file) == 6) {
                        continue;
                    }
                    $t_tgt_dir = $site_base_dir;
                } else{
                    $t_tgt_dir = $tgt_dir;
                }

                $src_file = $src_dir .$file;
                if (is_dir($src_file)) {            
                    exec(" mkdir -p " . escapeshellarg("$t_tgt_dir/$file"));
                } else if ($file == $htaccess_file) {
                    $tgt_file = $d_base_dir. "/etc/apache2/conf.d/$module.conf";
                    $apache_conf_file = "/etc/apache2/conf.d/$module.conf";
                    $conf_files[] = $apache_conf_file;
                    $htaccess= file_get_contents($src_file);
                    $has_rewrite = preg_match('/^\s*RewriteBase\s+(.+)\s*/m',$htaccess,$matches);
                    if ($has_rewrite) {
                        $pages = $site_base_dir  .'/' . $top_module . '/pages';
                        $rewrite = $matches[1];
                        $htaccess = "Alias $rewrite $pages\n<Directory $pages>\nDirectoryIndex index.php\n$htaccess\n</Directory>\n";
                        exec("mkdir -p $d_base_dir/etc/apache2/conf.d/");
                        file_put_contents($tgt_file,$htaccess);
                    } else {
                        I2CE::raiseError("No RewriteBase in $htaccess");
                    }
                } else  {
                    $tgt_file = "$t_tgt_dir/$file";
                    $dest_d = explode("/", $tgt_file);
                    array_pop($dest_d);
                    $dest_d = implode("/",$dest_d);
                    exec( "mkdir -p " . escapeshellarg($dest_d) );
                    exec("cp " . escapeshellarg($src_file) . ' ' . escapeshellarg($tgt_file));

                }
            }

            $config_file = false;
            if ($is_site_module && $has_rewrite && !$storage->setIfIsSet($config_file,"/config/data/$module/file")) {
                I2CE::raiseError( "No config file for $module -- Skipping site creation");
            }
            if ($is_site_module && $has_rewrite && $config_file) {
                I2CE::raiseError("Creating site for $module");
                $config_inc_file = "pages/config.values.php";
                if (!$database) {
                    $database =  preg_replace('/[^a-zA-Z0-9_]/','_',$module . '_' . $releases[$b_module]['sub_major_version']);
                }
                $config_db_dir = "/etc/ihris";
                $config_db_file = $config_db_dir . "/{$pkg_name}.config-db.php";

                if ($rc) {
                    $config = '<?php
include_once("' . $config_db_file . '");  #include deb_conf generated db access to get password
$i2ce_site_i2ce_path = "/usr/lib/iHRIS-' . $rc . '/lib/' . $i2ce_sub_major_version . '/I2CE";  
$i2ce_site_dsn = "mysql://$dbuser:$dbpass@unix(/var/run/mysqld/mysqld.sock)/$dbname" ;
$i2ce_site_module_config = "' . $site_base_dir . '/' . $module . '/' . basename($config_file) . '";
';
                } else {
                    $config = '<?php
include_once("' . $config_db_file . '");  #include deb_conf generated db access to get password
$i2ce_site_i2ce_path = "/usr/lib/iHRIS/lib/' . $i2ce_sub_major_version . '/I2CE";  
$i2ce_site_dsn = "mysql://$dbuser:$dbpass@unix(/var/run/mysqld/mysqld.sock)/$dbname" ;
$i2ce_site_module_config = "' . $site_base_dir . '/' . $module . '/' . basename($config_file) . '";
';
                }
                $config_tgt = $tgt_dir . '/' . $config_inc_file;
                $config_dir = explode("/", $config_tgt);
                array_pop($config_dir);
                $config_dir = implode("/" , $config_dir);
                exec("mkdir -p $config_dir");
                file_put_contents($config_tgt, $config);


                $mysql_dir = $d_base_dir . '/usr/share/dbconfig-common/data/' . $pkg_name .'/install-dbadmin';
                exec("mkdir -p $mysql_dir");
                $mysql_file = $mysql_dir . "/mysql";
                file_put_contents($mysql_file,"SET GLOBAL log_bin_trust_function_creators = 1;"); //needs to be run as admin
                exec("mkdir -p " . $d_base_dir. $config_db_dir);

                if (!array_key_exists('config',$scripts) || !$scripts['config']) {
                    I2CE::raiseError("Creating config script for $module");
                    $scripts['config'] = '#!/bin/sh 
# config script for ' . $pkg_name . '
set -e

. /usr/share/debconf/confmodule
. /usr/share/dbconfig-common/dpkg/config.mysql

if ! dbc_go ' . $pkg_name . ' $@ ; then
        echo \'Automatic configuration of ' . $pkg_name . ' using dbconfig-common failed!\'
fi

exit 0
';
                }

                if (!array_key_exists('postinst',$scripts) || !$scripts['postinst']) {
                    I2CE::raiseError("Creating postinst script for $module");
                    $scripts['postinst'] = '#!/bin/sh
# postinst script for ' . $pkg_name . '
set -e


. /usr/share/debconf/confmodule
. /usr/share/dbconfig-common/dpkg/postinst.mysql

dbc_generate_include_owner="root:www-data"
dbc_generate_include_perms="0640"
dbc_generate_include=php:' . $config_db_file . '

if ! dbc_go ' . $pkg_name . ' $@ ; then
        echo \'Automatic configuration of ' . $pkg_name . ' using dbconfig-common failed!\'
fi


if [ -f /etc/init.d/apache2 ] ; then
       if [ -x /usr/sbin/invoke-rc.d ]; then
                invoke-rc.d apache2 reload 3>/dev/null || true
              else
                /etc/init.d/apache2 reload 3>/dev/null || true
             fi
fi
if [ -f /etc/init.d/memcached ] ; then
             if [ -x /usr/sbin/invoke-rc.d ]; then
                invoke-rc.d memcached restart 3>/dev/null || true
              else
                /etc/init.d/memcached restart 3>/dev/null || true
            fi
fi
exit 0
';
                }
            }

        
            if (!array_key_exists('rules',$scripts) || !$scripts['rules']) {
                $scripts['rules'] =  "#!/usr/bin/make -f
# Uncomment this to turn on verbose mode.
export DH_VERBOSE=1

REVISION := $(shell head -1 debian/changelog | sed 's/.*(//;s/).*//;s/.*-//')

build: build-stamp
build-stamp:
\tdh_testdir
\ttouch build-stamp

clean: 
\tdh_testdir
\tdh_testroot
\trm -f build-stamp
\tdh_clean 

install: build
\tdh_testdir
\tdh_testroot
\tdh_prep
\tdh_installdirs
\tdh_install

# Build architecture-independent files here.
binary-indep: build install
\tdh_testdir
\tdh_testroot
\tdh_installchangelogs
\tdh_installdocs
\tdh_installdebconf
\tdh_link
\tdh_compress
\tdh_fixperms
\tdh_installdeb
\tdh_gencontrol
\tdh_md5sums
\tdh_builddeb

# Build architecture-dependent files here.
binary-arch:

binary: binary-indep binary-arch
.PHONY: build clean binary-indep binary-arch binary install
";
            }
            foreach (array("links","rules","config","postinst","preinst","postrm","prerm","conffiles") as $script) {
                $deb_options_script = $deb_options_dir . '/' . $script;
                if (is_readable($deb_options_script)) {
                    $scripts[$script] = file_get_contents($deb_options_script); //overrides what was done above!
                }
                if (array_key_exists($script,$scripts) && $scripts[$script]) {
                    $tgt_script_file = $d_dir .'/' . $script;
                    I2CE::raiseError("Putting $tgt_script_file");
                    file_put_contents($tgt_script_file,$scripts[$script]);
                    exec("chmod 755 $tgt_script_file");
                }
            }
        
            // if (count($conf_files) > 0) {
            //     $conf_file = $d_dir . '/conffiles';
            //     file_put_contents($conf_file, implode("\n",$conf_files)  . "\n");
            // }
        
            if (count($recommends) > 0) {
                $recommends = "Recommends: " . implode(",",$recommends) . "\n";
            } else {
                $recommends = '';
            }
            if (count($conflicts) > 0) {
                $conflicts = "Conflicts: " . implode(",",$conflicts) . "\n";
            } else {
                $conflicts = '';
            }
            if (count($breaks) > 0) {
                $breaks = "Breaks: " . implode(",",$breaks) . "\n";
            } else {
                $breaks = '';
            }


            if (is_dir($deb_sources_dir )) {
                exec("cp -R $deb_sources_dir/* $d_base_dir/");
            }
            $install = explode("\n",trim(shell_exec("/bin/ls -1 $d_base_dir | grep -v debian")));
            foreach ($install as &$each) {
                $each = trim($each) . '/* ' . trim($each);
            }
            unset($each);
            $i_file = $d_dir .'/install';
            file_put_contents($i_file,implode("\n",$install) . "\n");

            $c_file = $d_dir .'/control';
            $control = 
                "Source: " . $pkg_name ."\n"
                ."Maintainer: Carl Leitner <litlfred@ibiblio.org>\n"
                ."Section: web\n"
                ."Priority: optional\n"
                ."Standards-Version: 3.9.1\n"
                ."Build-Depends: debhelper (>= 7)\n"
                ."Homepage: http://www.capacityproject.org/hris/suite/\n"
                ."\nPackage: " . $pkg_name ."\n"
                ."Architecture: all\n"
                ."Depends: " . implode(",",$depends) . "\n"
                .$recommends
                .$conflicts
                .$breaks
                ."Description: $desc\n";
        
            file_put_contents($c_file,$control);

            $out = array();
            I2CE::raiseError("Buildiing signed source for $module on $ubuntu");
            $ret = 0;
            exec("cd $deb_src_dir/$ubuntu/$pkg_dir && dpkg-buildpackage $keyid -S -sa > /dev/null",$out,$ret);
        
            if ($ret != 0) {
                I2CE::raiseError("$module failed dpkg-build[$ret]:\n\t" . implode("\n\t",$out));
                continue;
            }
            if (!prompt("Would you like to upload $pkg_name for $ubuntu to ppa:{$launchpad_login}/$ppa",$do_upload)) {
                continue;
            }
            I2CE::raiseError("Uploading $package to PPA $ppa for $ubuntu");
            $out = array();
            
            $short_pkg = $pkg_name . '_' . substr($pkg_dir,strlen($pkg_name) +1);  //change it from packge-version to package_version 
            $cmd = " dput --force ppa:{$launchpad_login}/$ppa  $deb_src_dir/$ubuntu/{$short_pkg}_source.changes" ;
            exec ($cmd, $out,$ret);
            if ($ret != 0) {
                I2CE::raiseError(basename($dir) . " failed dput [$ret]:\n\t" . implode("\n\t",$out));
            }
        }
    }
}





function vers_compare($vers1,$vers2) {
    if (I2CE_Validate::checkVersion($vers1,'=',$vers2)) { 
        return 0;
    } 
    if (I2CE_Validate::checkVersion($vers1,"<",$vers2)) {
        return 1;
    } else {
        return -1;
    }
}


function current_vers_compare($vers1,$vers2) {
    if ($vers1 == 'current') {
        if ($vers2 == 'current') {
            return 0;
        }
        return -1; //current is bigger than anything else.  we want it to be at the beginning
    }
    if ($vers2 == 'current') {
        return 1;
    }
    if (I2CE_Validate::checkVersion($vers1,'=',$vers2)) { 
        return 0;
    } 
    if (I2CE_Validate::checkVersion($vers1,"<",$vers2)) { 
        return 1; 
    } else {
        return -1; //vers1 is bigger than vers2
    }
}

function makeSiteUser($module) {
    global $rc;
    $module = strtolower($module);
    $module = preg_replace('/[^a-z0-9]/','',$module);
    if (substr($module,0,5) != 'ihris') {
        if ($rc) {
            $module = 'ihris' . $rc. $module;
        } else {
            $module = 'ihris' . $module;
        }
    } else {
        if ($rc) {
            $module = 'ihris' . $rc . substr($module,6);
        }
    }
    return $module;
    
}

function makePackageName($module) {
    global $rc;
    $module = strtolower($module);
    $module = preg_replace('/--+/','-',preg_replace('/[^a-z0-9\\+\\.]/','-',$module));
    
    $module = trim($module,'-');
    if ($rc) {
        return   'ihris-' . $rc .'+'   . $module;
    } else {
        return   'ihris+'   . $module;

    }
}
