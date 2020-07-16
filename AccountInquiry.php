
<?php
/* $Id: GLAccountInquiry.php 7477 2017/1/21 19:33:38Z chengjiang exsonqu $*/
/*
 * @Author: ChengJiang 
 * @Date: 2017-07-11 11:10:09 
 * @Last Modified by: ChengJiang
 * @Last Modified time: 2017-12-15 05:36:39
 * 发行版 distro
 */
include ('includes/session.php');
$Title = _('General Ledger Account Inquiry');
$ViewTopic = 'GeneralLedger';
$BookMark = 'GLAccountInquiry';

//include('includes/GLPostings.inc');

if (isset($_POST['Account'])){
	$SelectedAccount = $_POST['Account'];
} elseif (isset($_GET['Account'])){
	$SelectedAccount = $_GET['Account'];
}
		
if (isset($_POST['period'])){
	$SelectedPeriod = $_POST['period'];
} elseif (isset($_GET['Period'])){
	$SelectedPeriod = array($_GET['Period']);
}

/* Get the start and periods, depending on how this script was called*/
if (isset($SelectedPeriod)) { //If it was called from itself (in other words an inquiry was run and we wish to leave the periods selected unchanged
	$FirstPeriodSelected = min($SelectedPeriod);
	$LastPeriodSelected = max($SelectedPeriod);
} elseif (isset($_GET['FromPeriod'])) { //If it was called from the Trial Balance/P&L or Balance sheet
	$FirstPeriodSelected = $_GET['FromPeriod'];
	$LastPeriodSelected = $_GET['ToPeriod'];
} else { // Otherwise just highlight the current period
	$FirstPeriodSelected =$_SESSION['period'];// GetPeriod(date($_SESSION['DefaultDateFormat']), $db);
	$LastPeriodSelected = $_SESSION['period'];//GetPeriod(date($_SESSION['DefaultDateFormat']), $db);
}
$sql="SELECT sum(amount) bfwd FROM gltrans where account='" . $SelectedAccount . "' and periodno< '" . $FirstPeriodSelected . "'";

		$ErrMsg = _('The chart details for account') . ' ' . $SelectedAccount . ' ' . _('could not be retrieved');
		$Result = DB_query($sql,$ErrMsg);
		$Row = DB_fetch_row($Result);

		$RunningTotal 	= $Row[0];
		$sql= "SELECT counterindex,
		type,
		typename,
		transno,
		gltrans.typeno,
		trandate,
		narrative,
		amount,
		toamount(amount,-1,0,0,1,flg) debit,
		toamount(amount,-1,0,0,-1,flg) credit,
		periodno,
		gltrans.tag			
	FROM gltrans INNER JOIN systypes
	ON systypes.typeid=abs(gltrans.type)			
	WHERE gltrans.account = '" . $SelectedAccount . "'
	AND periodno>='" . $FirstPeriodSelected . "'
	AND periodno<='" . $LastPeriodSelected . "'";
/*LEFT JOIN tags ON gltrans.tag = tags.tagref	tagdescription
if ($_POST['tag']!=0) {
 $sql = $sql . " AND tag='" . $_POST['tag'] . "'";
}
*/
$sql = $sql . " ORDER BY periodno, gltrans.trandate, counterindex";

$namesql = "SELECT accountname FROM chartmaster WHERE accountcode='" . $SelectedAccount . "'";
$nameresult = DB_query($namesql);
$namerow=DB_fetch_array($nameresult);
$SelectedAccountName=$namerow['accountname'];
$ErrMsg = _('The transactions for account') . ' ' . $SelectedAccount . ' ' . _('could not be retrieved because') ;
//prnMsg($sql,'info');
$TransResult = DB_query($sql,$ErrMsg);
//prnMsg($RunningTotal,'info');
if (isset($_POST['CSV'])) {

		$CSVListing =iconv('utf-8','gb2312', '序号,日期,凭证号,摘要,借方金额,贷方金额,借/贷,余额')."\n";
		$CSVListing .= '" "," "," ",'.iconv('utf-8','gb2312','期初结余').',"","",'.iconv('utf-8','gb2312',(($RunningTotal >= 0)?'借':'贷')).',"'.$RunningTotal.'"'."\n";
		$idx=1;
		while ($row = DB_fetch_array($TransResult)) {
			$RunningTotal += $row['amount'];			
			$PeriodTotal += $row['amount'];
			//	$DebitAmount = locale_number_format($row['debit'],$_SESSION['CompanyRecord']['decimalplaces']);
				$DebitSum +=$row['debit'];
			//	$CreditAmount = locale_number_format($row['credit'],$_SESSION['CompanyRecord']['decimalplaces']);
				$CreditSum +=  $row['credit'] ;
			$CSVListing .=$idx.','.$row['trandate'].','.$row['transno'].','.iconv('utf-8','gb2312',$row['narrative']).','.($row['debit']).','.($row['credit']).','.iconv('utf-8','gb2312',(($RunningTotal >= 0)?'借':'贷')).','.(abs($RunningTotal)) . "\n";
			$idx++;
		}
		$CSVListing .= '" "," "," ",'.iconv('utf-8','gb2312','累计').','.$DebitSum.','.$CreditSum.',"",""'."\n";
		header('Content-Encoding: gb2312');
		header('Content-type: text/csv; charset=gb2312');

		header("Content-Disposition: attachment; filename=".iconv('utf-8','gb2312','账簿') .  $SelectedAccount  . '-' . $LastPeriodSelected  .'.csv');
		header('Cache-Control:must-revalidate,post-check=0,pre-check=0');   
		header("Expires: 0");
		header("Pragma: public");
		//echo "\xEF\xBB\xBF"; // UTF-8 BOM
	
		echo $CSVListing;
		exit;
	
}
include('includes/header.php');
echo '<p class="page_title_text"><img src="'.$RootPath.'/css/'.$Theme.'/images/transactions.png" title="' . _('General Ledger Account Inquiry') . '" alt="" />' . ' ' . _('General Ledger Account Inquiry') . '</p>';

echo '<div class="page_help_text">' . _('Use the keyboard Shift key to select multiple periods') . '</div><br />';

echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '">';
echo '<div>';
echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

/*Dates in SQL format for the last day of last month*/
//$DefaultPeriodDate = Date ('Y-m-d', Mktime(0,0,0,Date('m'),0,Date('Y')));

/*Show a form to allow input of criteria for TB to show */
echo '<table class="selection">
		<tr>
			<td>' . _('Account').':</td>
			<td><select name="Account">';

			$sql="SELECT t3.accountname, t3.accountcode FROM chartmaster t3 WHERE t3.accountcode not in(SELECT t.accountcode FROM chartmaster t WHERE (LENGTH(t.accountcode)=4 or EXISTS
( select * from chartmaster t1 where locate(t.accountcode,t1.accountcode,1)>0 AND (LENGTH(t1.accountcode)>LENGTH(t.accountcode)) ))) and t3.accountcode like'".$SelectedAccount."%'";
$result = DB_query($sql);
while ($myrow=DB_fetch_array($result,$db)){
	if($myrow['accountcode'] == $SelectedAccount){

		echo '<option selected="selected" value="' . $myrow['accountcode'] . '">' . $myrow['accountcode'] . ' ' . htmlspecialchars($myrow['accountname'], ENT_QUOTES, 'UTF-8', false) . '</option>';
	} else {
		echo '<option value="' . $myrow['accountcode'] . '">' . $myrow['accountcode'] . ' ' . htmlspecialchars($myrow['accountname'], ENT_QUOTES, 'UTF-8', false) . '</option>';
	}
 }
echo '</select></td>
	</tr>';
echo '<tr>
		<td>' . _('For Period range').':</td>
		<td><select name="period[]" size="12" multiple="multiple">';

$sql = "SELECT periodno, lastdate_in_period FROM periods where periodno>=".$_SESSION['startperiod']. "  AND periodno<=".$_SESSION['period']. " ORDER BY periodno DESC";
$result = DB_query($sql);
while ($myrow=DB_fetch_array($result,$db)){
	if (isset($FirstPeriodSelected) AND $myrow['periodno'] >= $FirstPeriodSelected AND $myrow['periodno'] <= $LastPeriodSelected) {
		echo '<option selected="selected" value="' . $myrow['periodno'] . '">' . _(MonthAndYearFromSQLDate($myrow['lastdate_in_period'])) . '</option>';
	} else {
		echo '<option value="' . $myrow['periodno'] . '">' . _(MonthAndYearFromSQLDate($myrow['lastdate_in_period'])) . '</option>';
	}
}
echo '</select></td></tr>
	</table>
	<br />
	<div class="centre">
		<input type="submit" name="Show" value="'._('Show Account Transactions').'" />
		<input type="submit" name="CSV" value="导出CSV" /><br/>
		<a href="' . $RootPath . '/SelectGLAccount.php?Act='.substr($SelectedAccount,0,4).'">返回账簿查询</a>
		
	</div>
	</div>
	</form>';
	
//<input type="submit" name="submitreturn" value="' ._('Return').'" />
/* End of the Form  rest of script is what happens if the show button is hit*/

if (isset($_POST['Show']) OR isset($_POST['CSV'])){

	if (!isset($SelectedPeriod)){
		prnMsg(_('A period or range of periods must be selected from the list box'),'info');
		include('includes/footer.php');
		exit;
	}
	/*Is the account a balance sheet or a profit and loss account */
	$result = DB_query("SELECT pandl
				FROM accountgroups
				INNER JOIN chartmaster ON accountgroups.groupname=chartmaster.group_
				WHERE chartmaster.accountcode='" . $SelectedAccount ."'");
	$PandLRow = DB_fetch_row($result);
	if ($PandLRow[0]==1){
		$PandLAccount = True;
	}else{
		$PandLAccount = False; /*its a balance sheet account */
	}

	$FirstPeriodSelected = min($SelectedPeriod);
	$LastPeriodSelected = max($SelectedPeriod);
	
	/*
	$sql= "SELECT counterindex,
				type,
				typename,
				transno,
				gltrans.typeno,
				trandate,
				narrative,
				amount,
				toamount(amount,-1,0,0,1,flg) debit,
				toamount(amount,-1,0,0,-1,flg) credit,
				periodno,
				gltrans.tag			
			FROM gltrans INNER JOIN systypes
			ON systypes.typeid=abs(gltrans.type)			
			WHERE gltrans.account = '" . $SelectedAccount . "'
			AND posted=1
			AND periodno>='" . $FirstPeriodSelected . "'
			AND periodno<='" . $LastPeriodSelected . "'";

	$sql = $sql . " ORDER BY periodno, gltrans.trandate, counterindex";

	$namesql = "SELECT accountname FROM chartmaster WHERE accountcode='" . $SelectedAccount . "'";
	$nameresult = DB_query($namesql);
	$namerow=DB_fetch_array($nameresult);
	$SelectedAccountName=$namerow['accountname'];
	$ErrMsg = _('The transactions for account') . ' ' . $SelectedAccount . ' ' . _('could not be retrieved because') ;
	//prnMsg($sql,'info');
	$TransResult = DB_query($sql,$ErrMsg);*/
	$BankAccountInfo = isset($BankAccount)?'<th>' . _('Org Currency') . '</th>
						<th>' . _('Amount in Org Currency') . '</th>	
						<th>' . _('Bank Ref') .'</th>':'';
	echo '<br />
		<table class="selection">
		<thead>
			<tr>
				<th colspan="7"><b>', _('Transactions for account'), ' ', $SelectedAccount, ' - ', $SelectedAccountName, '</b></th>
			</tr>
			<tr>
				<th class="centre">', ('Date'), '</th>
				<th class="text">', _('Voucher No'), '</th>
				<th class="text">', _('Narrative'), '</th>			
				<th class="number">', _('Debit'), '</th>
				<th class="number">', _('Credit'), '</th>		
			  	<th class="text">', _('Tag'), '</th>
				<th class="number">', _('Balance'), '</th>				
			</tr>
		</thead><tbody>';
	//	$RunningTotal =$_POST['bfwd'];
	if ($PandLAccount==True) {
		$RunningTotal = 0;
	} else {
		
   
			echo '<tr>
					<td colspan="3"><b>', _('Brought Forward Balance'), '</b></td>
				';
		if($RunningTotal < 0 ) {// It is a credit balance b/fwd
			echo '	<td>&nbsp;</td>
					<td class="number"><b>', locale_number_format(-$RunningTotal,$_SESSION['CompanyRecord']['decimalplaces']), '</b></td>
					<td colspan="3">&nbsp;</td>
				</tr>';
		} else {// It is a debit balance b/fwd
			echo '	<td class="number"><b>', locale_number_format($RunningTotal,$_SESSION['CompanyRecord']['decimalplaces']), '</b></td>
					<td colspan="4">&nbsp;</td>
				</tr>';
		}
	}
	$PeriodTotal = 0;
	$PeriodNo = -9999;
	$ShowIntegrityReport = False;
	$j = 1;
	$k=0; //row colour counter
	$IntegrityReport='';
	while ($myrow=DB_fetch_array($TransResult)) {
		if ($myrow['periodno']!=$PeriodNo){
			if ($PeriodNo!=-9999){ //ie its not the first time around
				echo '<tr>
					<td colspan="3"><b>' . _('Total for period') . ' </b></td>';
			   echo '<td class="number"><b>', locale_number_format($DebitSum,$_SESSION['CompanyRecord']['decimalplaces']), '</b></td>
					 <td class="number"><b>', locale_number_format($CreditSum,$_SESSION['CompanyRecord']['decimalplaces']), '</b></td>
					 <td colspan="2">&nbsp;</td>
						</tr>';
				$IntegrityReport = '<br />' . _('Period') . ': ' . $PeriodNo  . _('Account movement per transaction') . ': '  . locale_number_format($PeriodTotal,$_SESSION['CompanyRecord']['decimalplaces']) . ' ' . _('Movement per ChartDetails record') . ': ' . locale_number_format($ChartDetailRow['actual'],$_SESSION['CompanyRecord']['decimalplaces']) . ' ' . _('Period difference') . ': ' . locale_number_format($PeriodTotal -$ChartDetailRow['actual'],3);
			}
			$PeriodNo = $myrow['periodno'];
			$PeriodTotal = 0;
			$DebitSum = 0;
			$CreditSum = 0;
		}

		if ($k==1){
			echo '<tr class="EvenTableRows">';
			$k=0;
		} else {
			echo '<tr class="OddTableRows">';
			$k++;
		}

		$RunningTotal += $myrow['amount'];
		$PeriodTotal += $myrow['amount'];
			$DebitAmount = locale_number_format($myrow['debit'],$_SESSION['CompanyRecord']['decimalplaces']);
			$DebitSum +=$myrow['debit'];
			$CreditAmount = locale_number_format($myrow['credit'],$_SESSION['CompanyRecord']['decimalplaces']);
	        $CreditSum +=  $myrow['credit'] ;
        /*
		if($myrow['amount']>=0){
			$DebitAmount = locale_number_format($myrow['amount'],$_SESSION['CompanyRecord']['decimalplaces']);
			$CreditAmount = '';
			$DebitSum +=$myrow['amount'];
			
		} else {
			$CreditAmount = locale_number_format(-$myrow['amount'],$_SESSION['CompanyRecord']['decimalplaces']);
			$DebitAmount = '';
		  $CreditSum += - $myrow['amount'] ;
		}
        */
		$FormatedTranDate = ConvertSQLDate($myrow['trandate']);
		$URL_to_TransDetail = $RootPath . '/GLTransInquiry.php?prdno=' . $myrow['periodno'] . '&amp;TransNo=' . $myrow['transno'];
		//if (isset($BankAccount)) {
			printf('<td class="centre">%s</td>
				<td class="text"><a href="%s" target="_blank" >%s</a></td>
				<td class="text">%s</td>				
				<td class="number">%s</td>
				<td class="number">%s</td>
				<td class="text">%s</td>				
				<td class="number">%s</td>			
				</tr>',
		    	$FormatedTranDate,
				$URL_to_TransDetail,_('Accounting'). $myrow['transno']._($myrow['typename']).$myrow['typeno'],
				$myrow['narrative'],
				$DebitAmount,
				$CreditAmount,
				($RunningTotal >= 0)?_('Debit'):_('Credit'),
				locale_number_format(($RunningTotal >= 0)? $RunningTotal:-$RunningTotal,$_SESSION['CompanyRecord']['decimalplaces']));
	}
 				echo '<tr>
					<td colspan="3"><b>' . _('Total for period') . ' </b></td>';
			   	echo '<td class="number"><b>', locale_number_format($DebitSum,$_SESSION['CompanyRecord']['decimalplaces']), '</b></td>
					 <td class="number"><b>', locale_number_format($CreditSum,$_SESSION['CompanyRecord']['decimalplaces']), '</b></td>
					 <td colspan="2">&nbsp;</td>
					</tr>';

	echo '</tbody></table>';
	
}
/*elseif (isset($_POST['submitreturn'])) {
	
		header('Location:SelectGLAccount.php?Account='.$SelectedAccount);
} */

if (isset($ShowIntegrityReport) AND $ShowIntegrityReport==True ){
	if (!isset($IntegrityReport)) {
		$IntegrityReport='';
	}
	prnMsg( _('There are differences between the sum of the transactions and the recorded movements in the ChartDetails table') . '. ' . _('A log of the account differences for the periods report shows below'),'warn');
	echo '<p>' . $IntegrityReport;
}
include('includes/footer.php');
?>
