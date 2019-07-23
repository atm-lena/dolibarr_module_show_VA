<?php
/* Copyright (C) 2019 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
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

require 'config.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('show/class/show.class.php');
dol_include_once('show/class/showcategory.class.php');
dol_include_once('show/lib/show.lib.php');
dol_include_once('product/class/product.class.php');

if(empty($user->rights->show->read)) accessforbidden();

$langs->load('show@show');

$action = GETPOST('action');
$id = GETPOST('id', 'int');
$ref = GETPOST('ref');
$price = GETPOST('price');
$category = GETPOST('fk_c_show_category');
$product = GETPOST('product', 'int');

$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'showcard';   // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha');

$object = new show($db);

if (!empty($id) || !empty($ref)) $object->fetch($id, true, $ref);

$hookmanager->initHooks(array('showcard', 'globalcard'));


if ($object->isextrafieldmanaged)
{
    $extrafields = new ExtraFields($db);

    $extralabels = $extrafields->fetch_name_optionals_label($object->table_element);
    $search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');
}

// Initialize array of search criterias
//$search_all=trim(GETPOST("search_all",'alpha'));
//$search=array();
//foreach($object->fields as $key => $val)
//{
//    if (GETPOST('search_'.$key,'alpha')) $search[$key]=GETPOST('search_'.$key,'alpha');
//}

/*
 * Actions
 */

$parameters = array('id' => $id, 'ref' => $ref);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

// Si vide alors le comportement n'est pas remplacé
if (empty($reshook))
{
	$error = 0;
	switch ($action) {
		case 'add':

		    global $conf;

		    //Default prices
		    if($price=='') {
                if ($category != 0) {
                    $cat = new showcategory($db);
                    $cat->fetch($category);
                    $_REQUEST['price'] = $cat->default_price;
                } else {
                    $_REQUEST['price'] = $conf->global->SHOW_DEFAULTPRICE;
                }
            }

            $object->setValues($_REQUEST);

        case 'update':

            //Default prices
            if($price=='') {
                if ($category != 0) {
                    $cat = new showcategory($db);
                    $cat->fetch($category);
                    $_REQUEST['price'] = $cat->default_price;
                } else {
                    $_REQUEST['price'] = $conf->global->SHOW_DEFAULTPRICE;
                }
            }

			$object->setValues($_REQUEST); // Set standard attributes

            if ($object->isextrafieldmanaged)
            {
                $ret = $extrafields->setOptionalsFromPost($extralabels, $object);
                if ($ret < 0) $error++;
            }

//			$object->date_other = dol_mktime(GETPOST('starthour'), GETPOST('startmin'), 0, GETPOST('startmonth'), GETPOST('startday'), GETPOST('startyear'));

			// Check parameters
//			if (empty($object->date_other))
//			{
//				$error++;
//				setEventMessages($langs->trans('warning_date_must_be_fill'), array(), 'warnings');
//			}
			
			// ...

			if ($error > 0)
			{
				$action = 'edit';
				break;
			}
			
			$res = $object->save($user);
            if ($res < 0)
            {
                setEventMessage($object->errors, 'errors');
                if (empty($object->id)) $action = 'create';
                else $action = 'edit';
            }
            else
            {
                header('Location: '.dol_buildpath('/show/card.php', 1).'?id='.$object->id);
                exit;
            }
        case 'update_extras':

            $object->oldcopy = dol_clone($object);

            // Fill array 'array_options' with data from update form
            $ret = $extrafields->setOptionalsFromPost($extralabels, $object, GETPOST('attribute', 'none'));
            if ($ret < 0) $error++;

            if (! $error)
            {
                $result = $object->insertExtraFields('MYMODULE_MODIFY');
                if ($result < 0)
                {
                    setEventMessages($object->error, $object->errors, 'errors');
                    $error++;
                }
            }

            if ($error) $action = 'edit_extras';
            else
            {
                header('Location: '.dol_buildpath('/show/card.php', 1).'?id='.$object->id);
                exit;
            }
            break;
		case 'confirm_clone':
			$object->cloneObject($user);
			
			header('Location: '.dol_buildpath('/show/card.php', 1).'?id='.$object->id);
			exit;

		case 'modif':
		case 'reopen':
			if (!empty($user->rights->show->write)) $object->setDraft($user);
				
			break;
		case 'confirm_validate':
			if (!empty($user->rights->show->write)) $object->setValid($user);
			
			header('Location: '.dol_buildpath('/show/card.php', 1).'?id='.$object->id);
			exit;

		case 'confirm_delete':
			if (!empty($user->rights->show->delete)) $object->delete($user);
			
			header('Location: '.dol_buildpath('/show/list.php', 1));
			exit;

		// link from llx_element_element
		case 'dellink':
			$object->deleteObjectLinked(null, '', null, '', GETPOST('dellinkid'));
			header('Location: '.dol_buildpath('/show/card.php', 1).'?id='.$object->id);
			exit;

	}
}


/**
 * View
 */
$form = new Form($db);

$title=$langs->trans('show');
llxHeader('', $title);

if ($action == 'create')
{

    //Create a show from a product
    if($product) {
        $prod = new Product($db);
        $prod->fetch($product);

        $_POST['ref'] = $prod->ref;
        $_POST['label'] = $prod->label;
        $_POST['price'] = $prod->price;
    }

    print load_fiche_titre($langs->trans('Newshow'), '', 'show@show');

    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="add">';
    print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';

    dol_fiche_head(array(), '');

    print '<table class="border centpercent">'."\n";

    // Common attributes
    include DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_add.tpl.php';

    // Other attributes
    include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_add.tpl.php';

    // Category attribute
    if ($conf->categorie->enabled) {
        print '<tr><td>'.$langs->trans("Categories").'</td><td colspan="3">';
        print select_all_categories();
        print "</td></tr>";
    }

    print '<input type="hidden" id="fk_product" name="fk_product" value='.$product.'>';

    print '</table>'."\n";

    dol_fiche_end();

    print '<div class="center">';
    print '<input type="submit" class="button" name="add" value="'.dol_escape_htmltag($langs->trans('Create')).'">';
    print '&nbsp; ';
    print '<input type="'.($backtopage?"submit":"button").'" class="button" name="cancel" value="'.dol_escape_htmltag($langs->trans('Cancel')).'"'.($backtopage?'':' onclick="javascript:history.go(-1)"').'>';	// Cancel for create does not post form if we don't know the backtopage
    print '</div>';

    print '</form>';
}
else
{
    if (empty($object->id))
    {
        $langs->load('errors');
        print $langs->trans('ErrorRecordNotFound');
    }
    else
    {
        if (!empty($object->id) && $action === 'edit')
        {
            print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
            print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
            print '<input type="hidden" name="action" value="update">';
            print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
            print '<input type="hidden" name="id" value="'.$object->id.'">';

            $head = show_prepare_head($object);
            $picto = 'show@show';
            dol_fiche_head($head, 'card', $langs->trans('show'), 0, $picto);

            print '<table class="border centpercent">'."\n";

            // Common attributes
            include DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_edit.tpl.php';

            // Other attributes
            include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_edit.tpl.php';

            // Category attribute
            if ($conf->categorie->enabled) {
                print '<tr><td>'.$langs->trans("Categories").'</td><td colspan="3">';
                print select_all_categories($object->fk_c_show_category);
                print "</td></tr>";
            }

            print '</table>';

            dol_fiche_end();

            print '<div class="center"><input type="submit" class="button" name="save" value="'.$langs->trans('Save').'">';
            print ' &nbsp; <input type="submit" class="button" name="cancel" value="'.$langs->trans('Cancel').'">';
            print '</div>';

            print '</form>';
        }
        elseif ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create')))
        {
            $head = show_prepare_head($object);
            $picto = 'show@show';
            dol_fiche_head($head, 'card', $langs->trans('show'), -1, $picto);

            $formconfirm = getFormConfirmshow($form, $object, $action);
            if (!empty($formconfirm)) print $formconfirm;


            $linkback = '<a href="' .dol_buildpath('/show/list.php', 1) . '?restore_lastsearch_values=1">' . $langs->trans('BackToList') . '</a>';

            $morehtmlref='<div class="refidno">';
            /*
            // Ref bis
            $morehtmlref.=$form->editfieldkey("RefBis", 'ref_client', $object->ref_client, $object, $user->rights->show->write, 'string', '', 0, 1);
            $morehtmlref.=$form->editfieldval("RefBis", 'ref_client', $object->ref_client, $object, $user->rights->show->write, 'string', '', null, null, '', 1);
            // Thirdparty
            $morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . $soc->getNomUrl(1);
            */
            $morehtmlref.='</div>';


            $morehtmlstatus.=$object->getLibStatut(2);
            dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '', 0, '', $morehtmlstatus);

            print '<div class="fichecenter">';

            print '<div class="fichehalfleft">'; // Auto close by commonfields_view.tpl.php
            print '<div class="underbanner clearboth"></div>';
            print '<table class="border tableforfield" width="100%">'."\n";


            // Common attributes
            //$keyforbreak='fieldkeytoswithonsecondcolumn';
            include DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_view.tpl.php';

            // Other attributes
            include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_view.tpl.php';

            //fk_c_show_category attribute
            if($object->fk_c_show_category != 0) {
                $cat = new showcategory($db);
                $cat->fetch($object->fk_c_show_category);
                print '<tr>';
                print '<td class ="titlefield">' . $langs->trans('Category') . '</td>';
                print '<td>';
                print $cat->label;
                print '</a></td>';
                print '</tr>';
            }

            //fk_product attribute
            if($object->fk_product) {
                $product = new Product($db);
                $product->fetch($object->fk_product);
                print '<tr>';
                print '<td class ="titlefield">Produit associé</td>';
                print '<td>';
                print $product->getNomURL();
                print '</td>';
                print '</tr>';
            }

            print '</table>';

            print '</div></div>'; // Fin fichehalfright & ficheaddleft
            print '</div>'; // Fin fichecenter

            print '<div class="clearboth"></div><br />';

            print '<div class="tabsAction">'."\n";
            $parameters=array();
            $reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action);    // Note that $action and $object may have been modified by hook
            if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

            if (empty($reshook))
            {
                // Send
                //        print '<a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=presend&mode=init#formmailbeforetitle">' . $langs->trans('SendMail') . '</a>'."\n";

                // Modify
                if (!empty($user->rights->show->write))
                {
                    if ($object->status !== show::STATUS_CANCELED)
                    {
                        // Modify
                        if ($object->status !== show::STATUS_ACCEPTED) print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=edit">'.$langs->trans("showModify").'</a></div>'."\n";
                        // Clone
                        print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=clone">'.$langs->trans("showClone").'</a></div>'."\n";
                    }

                    // Valid
                    if ($object->status === show::STATUS_DRAFT) print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=valid">'.$langs->trans('showValid').'</a></div>'."\n";

                    // Accept
                    if ($object->status === show::STATUS_VALIDATED) print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=accept">'.$langs->trans('showAccept').'</a></div>'."\n";
                    // Refuse
                    if ($object->status === show::STATUS_VALIDATED) print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=refuse">'.$langs->trans('showRefuse').'</a></div>'."\n";


                    // Reopen
                    if ($object->status === show::STATUS_ACCEPTED || $object->status === show::STATUS_REFUSED) print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=reopen">'.$langs->trans('showReopen').'</a></div>'."\n";
                    // Cancel
                    if ($object->status === show::STATUS_VALIDATED) print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=cancel">'.$langs->trans("showCancel").'</a></div>'."\n";
                }
                else
                {
                    if ($object->status !== show::STATUS_CANCELED)
                    {
                        // Modify
                        if ($object->status !== show::STATUS_ACCEPTED) print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->trans("NotEnoughPermissions")).'">'.$langs->trans("showModify").'</a></div>'."\n";
                        // Clone
                        print '<div class="inline-block divButAction"><a class="butAction" href="#" title="'.dol_escape_htmltag($langs->trans("NotEnoughPermissions")).'">'.$langs->trans("showClone").'</a></div>'."\n";
                    }

                    // Valid
                    if ($object->status === show::STATUS_DRAFT) print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->trans("NotEnoughPermissions")).'">'.$langs->trans('showValid').'</a></div>'."\n";

                    // Accept
                    if ($object->status === show::STATUS_VALIDATED) print '<div class="inline-block divButAction"><a class="butActionRefused" href="#">'.$langs->trans('showAccept').'</a></div>'."\n";
                    // Refuse
                    if ($object->status === show::STATUS_VALIDATED) print '<div class="inline-block divButAction"><a class="butActionRefused" href="#">'.$langs->trans('showRefuse').'</a></div>'."\n";

                    // Reopen
                    if ($object->status === show::STATUS_ACCEPTED || $object->status === show::STATUS_REFUSED) print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->trans("NotEnoughPermissions")).'">'.$langs->trans('showReopen').'</a></div>'."\n";
                    // Cancel
                    if ($object->status === show::STATUS_VALIDATED) print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->trans("NotEnoughPermissions")).'">'.$langs->trans("showCancel").'</a></div>'."\n";
                }

                if (!empty($user->rights->show->delete))
                {
                    print '<div class="inline-block divButAction"><a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=delete">'.$langs->trans("showDelete").'</a></div>'."\n";
                }
                else
                {
                    print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->trans("NotEnoughPermissions")).'">'.$langs->trans("showDelete").'</a></div>'."\n";
                }
            }
            print '</div>'."\n";

            print '<div class="fichecenter"><div class="fichehalfleft">';
            $linktoelem = $form->showLinkToObjectBlock($object, null, array($object->element));
            $somethingshown = $form->showLinkedObjectBlock($object, $linktoelem);

            print '</div><div class="fichehalfright"><div class="ficheaddleft">';

            // List of actions on element
            include_once DOL_DOCUMENT_ROOT . '/core/class/html.formactions.class.php';
            $formactions = new FormActions($db);
            $somethingshown = $formactions->showactions($object, $object->element, $socid, 1);

            print '</div></div></div>';

            dol_fiche_end(-1);
        }
    }
}

function select_all_categories($selected=''){

    global $db, $langs;

    $category = new showcategory($db);

    $i = 0;
    foreach($category->fetchAll() as $line) {
        $categories[$i]['label'] = $line->label;
        $categories[$i]['id'] = $line->id;
        $i++;
    }

    $output = '<select class="flat" name="fk_c_show_category" id="fk_c_show_category">';

    if (is_array($categories))
    {
        if (! count($categories)) $output.= '<option value="0" disabled>'.$langs->trans("NoCategoriesDefined").'</option>';
        else
        {
            $output.= '<option value="0">&nbsp;</option>';
            foreach($categories as $key => $value)
            {
                if ($categories[$key]['id'] == $selected || ($selected == 'auto' && count($categories) == 1))
                {
                    $add = 'selected ';
                }
                else
                {
                    $add = '';
                }
                $output.= '<option '.$add.'value="'.$categories[$key]['id'].'">'.dol_trunc($categories[$key]['label'],64,'middle').'</option>';
            }
        }
    }
    $output.= '</select>';
    $output.= "\n";

    return $output;
}

llxFooter();
$db->close();
