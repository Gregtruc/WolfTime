<?php session_start(); 
include "connectdb.php";
include "basicconst.php";
if (!isset($_SESSION['uid']) /*or !$_SESSION['admin']*/) { header('Location: index.php'); exit(); }

if ($_SESSION['anciennete']==1 and !$_SESSION['assoc'] and !isset($_GET['race']))
	reload('?race=1');

if (isset($_SESSION['vraiuid']) and !$_SESSION['assoc']) { header('Location: index.php'); exit(); }
	
$_SESSION['pass']=mysql_result(mysql_query('SELECT pass FROM login WHERE id = '.$_SESSION['uid']),0);

if (isset($_GET['cid']) and ($_SESSION['anciennete']>=3 or $_SESSION['assoc']))
{
	$query = "SELECT vente,proprietaire,nom,reserve,compte,race,cid,sexe FROM chevaux LEFT JOIN login ON proprietaire = id WHERE cid = ".$_GET['cid'];
	$result = mysql_query($query);
	
	$row = mysql_fetch_array($result);
	
	$prix = $row[0];
	$check_remise=mysql_query('SELECT remise FROM sponsors WHERE aid = '.$row[1].' AND uid = '.$_SESSION['uid']);
	if ($rem=mysql_fetch_row($check_remise))
		$remise=$rem[0];
	else 
		$remise = 0;
	
	$prix = ceil((1-$remise/100)*$row[0]);
		
	if ($prix<=0)
	{
		if (!isset($_GET['rapide']))
			header('Location: acheter.php?erreur=1');
		exit();
	}
	else if ($row[3] and $row[3]!=$_SESSION['uid'])
	{
		if (!isset($_GET['rapide']))
			header('Location: acheter.php?erreur=4');
		exit();
	}
	else if ($row[1] == $_SESSION['uid'])
	{
		if (!isset($_GET['rapide']))
			header('Location: acheter.php?erreur=2');
		exit();
	}
	else if ($row[0] > $_SESSION['argent'])
	{
		if (!isset($_GET['rapide']))
			header('Location: acheter.php?erreur=3');
		exit();
	}
	else if ($row[1] == -1 and !$_SESSION['assoc'])
		reload();
	else if (isset($_SESSION['assoc']) and $_SESSION['assoc'] and $_SESSION['raceassoc']!=$row[5])
	{
		if (!isset($_GET['rapide']))
			header('Location: acheter.php?erreur=5');
		exit();
	}
	else 
	{
		$deja_vendu=mysql_result(mysql_query('SELECT COUNT(1) FROM hvente WHERE cid = '.$row['cid'].' AND vuid = '.$_SESSION['uid'].' LIMIT 1'),0);
		
		$query = 'UPDATE login SET argent = argent - '.$prix.', '.($deja_vendu?'ventes=ventes-1':'achats=achats+1').' WHERE id = '.$_SESSION['uid'];
		$result = mysql_query($query);
		
		if (mysql_affected_rows()!=1)
		{
			exit();
		}
		$query = 'UPDATE login SET doitrecache=GREATEST(doitrecache,3), argent = argent + '.$prix.', '.($deja_vendu?'achats=achats-1':'ventes=ventes+1').' WHERE id = '.$row[1];
		$result = mysql_query($query);
		$_SESSION['argent']=$_SESSION['argent']-$prix;
		
		if (majApresVente($_GET['cid'],$_SESSION['uid'])!=1)
		{
			$query = 'UPDATE login SET argent = argent + '.$prix.', '.($deja_vendu?'ventes=ventes+1':'achats=achats-1').' WHERE id = '.$_SESSION['uid'];
			exit();
		}
			
		$message = linkanimal($row).' a été vendu à <a class=atype5 href=fiche.php?uid='.$_SESSION['uid'].'>'.$_SESSION['compte'].'</a> au prix de '.$prix.'<img src=monnaie.png>';
		if ($remise)
			$message.=' (Au lieu de '.$row[0].'<img src=monnaie.png>, remise de '.$remise.'% pour son '.$SPONSORING.')';
		mysql_query('INSERT DELAYED INTO infos(uid,message,type) VALUES('.$row[1].',"'.$message.'",1)');
		mysql_query('UPDATE materiel SET cid = 0, equipable = 1 WHERE categorie = 2 AND cid = '.$_GET['cid']);	
		
		if (isset($_SESSION['assoc']) and $_SESSION['assoc'])
		{
			$message=$_SESSION['fichemoi'].' a acheté '.linkanimal($row).' à <a class=atype5 href=fiche.php?uid='.$row[1].'>'.$row[4].'</a> pour '.$row[0].'<img src=monnaie.png>.';
			mysql_query('UPDATE login SET historique=CONCAT(historique,"['.date('H\hi').']'.$message.'<br>") WHERE id = '.$_SESSION['uid']);
		}
		mysql_query('INSERT INTO hvente(cid,cnom,vuid,vunom,auid,aunom,prix) VALUES ('.$_GET['cid'].',"'.$row[2].'",'.$row[1].',"'.$row[4].'",'.$_SESSION['uid'].',"'.$_SESSION['compte'].'",'.$row[0].')');
		$_SESSION['doitrecache']=max($_SESSION['doitrecache'],3);;
		if (!isset($_GET['rapide']))
			header('Location: '.$ANIMAL.'.php?cid='.$_GET['cid']);
		else 
			echo formatNombre($_SESSION['argent']); 
		exit();
	}

}
include "header.php";
include "debutpage.php";
include "debutmenu.php";
include "menu.php";
include "finmenu.php";
include "debutaction.php";
echo debutAction();
?>
<center>Acheter des <a class="atype5" href="achetepass.php"><img border=0 src=pass.png></a></center><br>
<?php finAction().debutAction();
include "pub.php";
echo finAction();
include "finaction.php";
?>
<script type="text/javascript">
function description(id)
{
	montre("",600);
	GetId('bubulle').innerHTML = '<div style="width:600">'+GetId(id).innerHTML+'</div>'; 
}
</script>
<?php 
if ($_SESSION['anciennete']>1 or $_SESSION['assoc']):
echo debutFenetre('Hotel des ventes');?>
L'hotel des ventes présente les ventes directes de <?=$ANIMAUX?>. 
<?php 
if ($_SESSION['anciennete']>=3 or $_SESSION['assoc'])
	echo '<a class=atype5 href = "listevente2.php">Accéder à l\'hotel des ventes </a>';
else 
	echo '<br><br><span class=important>Disponible à partir de 3 jours d\'ancienneté.</span>';

echo finFenetre();
echo debutFenetre('La salle des enchères');?>
La salle des enchères regroupe les enchères de <?=$ANIMAUX?>. 
<?php 
if ($_SESSION['anciennete']>=3 or $_SESSION['assoc'])
	echo '<a class=atype5 href = "encheres.php">Accéder à la salle des enchères </a>';
else 
	echo '<br><br><span class=important>Disponible à partir de 3 jours d\'ancienneté.</span>';
echo finFenetre();

if ($_SESSION['assoc']) {
echo debutFenetre('Seconde vie');
echo 'Cette partie '.mot('venteanimaux','ofthe').' permet '.mot('association','to',0,1).' de racheter '.mot('animal','a',0,1).' cédés par des joueurs. <a class=atype5 href=secondevie.php>Accéder à la filiale seconde vie '.mot('venteanimaux','ofthe').'</a>';
echo finFenetre();
}
endif;
echo debutFenetre(ucfirst($LABARAQUE));?>
<span class=combleu>Choisissez un <?php echo $PETIT; ?> vendu <?php echo $PARLABARAQUE; ?></span><br>
<?php 
if (isset($_POST['sexe']) and isset($_POST['race']) and $_POST['race']!='' and $_POST['sexe']!='') 
{
	
	echo '<a class="atype2" href="acheter.php">Réinitialiser le choix</a>';
	echo '<br>';
	$inforace = mysql_fetch_assoc(mysql_query('SELECT nom, envente FROM race WHERE raceid = '.$_POST['race']));
	
	if ($SITE_ID==2 and !$inforace['envente'])
		echo 'Sexe : Couple<br>'; 
	else
		echo 'Sexe : '.(($_POST['sexe']==1) ? 'Mâle' : 'Femelle').'<br>';
	
	echo 'Race : <a class="atype6" href="inforace.php?race='.$_POST['race'].'">'.($inforace['nom']).'</a>';	

	echo '<br><br><span class="combleu">Choisissez votre robe :</span>';
	echo '<table cellspacing=0 cellpadding=0 id=achatrobes>';
	
    $query = "SELECT nom,robeid FROM robe WHERE disponible = 1 AND raceid = ".$_POST['race'];
	$result = mysql_query($query);

	$i=0;
	while($row = mysql_fetch_row($result))
	{
		$i++;
		echo '<tr '.($i % 2?' class="clair"':'').'>';
		echo '<td><img src="robes/'.$PETIT.$row[1].'.png" width=200px></td><td><img src="robes/robe';
		if ($_POST['race']==4 and $row[1]==3)
			echo 12;
		if ($_POST['race']==55 and $_POST['sexe']==2)
			echo ($row[1]+2);
		else echo $row[1];
		echo '.png" width=200px></td>';
		echo '<td width=200px><a class="atype5" href="achatpoulain.php?sexe='.$_POST['sexe'].'&race='.$_POST['race'].'&robe='.$row[1].'">Acheter un '.$PETIT.' '.mysql_result($rnomrace,0).' de robe '.$row[0].'</a></td>';
		
		echo '</tr>';
	}
	echo '</table>';
}
else if (isset($_GET['race']))
{
	$inforace = mysql_fetch_assoc(mysql_query('SELECT nom, envente FROM race WHERE raceid = '.$_GET['race']));
	
	
	echo '<form method="POST" name="choix">
		
		<table>
		<tr><td>Race : </td><td colspan=2><a class="atype6" href="inforace.php?race='.$_GET['race'].'">'.($inforace['nom']).'</a></td></tr>
		<tr><td width = 70px>Sexe : </td>';
		if ($SITE_ID==2 and !$inforace['envente'])
			echo '<td width = 100px><input type="radio" name="sexe" value="1" checked><a class="atypespe" href="#"  onclick="document.choix.sexe[0].checked=\'checked\'; return false;">Couple</a></td>';
		else
		echo '<td width = 100px><input type="radio" name="sexe" value="1"><a class="atypespe" href="#"  onclick="document.choix.sexe[0].checked=\'checked\'; return false;">Mâle</a></td> 
		<td width = 100px><input type="radio" name="sexe" value="2"><a class="atypespe" href="#"  onclick="document.choix.sexe[1].checked=\'checked\'; return false;">Femelle</a></td></tr>';
  		echo '</table>
  		<input type="hidden" name="race" value="'.$_GET['race'].'">
  		<input type="submit" value="Choisir la robe">
  		</form>';
}
else 
{
	echo '<span class=important>Les robes présentées sur cette page sont des exemples de robe disponible pour chaque race, d\'autres robes sont souvent disponibles.</span><br><br>Choisissez votre race :';
	
	function dispListRace($result)
	{
		echo '<table cellspacing=25><tr>';
		$i=0;
		while($row = mysql_fetch_row($result))
		{
			$i++;
			if (!(($i-1)%3) and $i>1)
				echo '</tr><tr>';
				
			$paslesmoyens=($row[2]?($_SESSION['argent']<($row[1]==1?1000:2000)):($_SESSION['pass']<1));
			echo '<td width = 180px align=center class="historique" style="-moz-border-radius: 10px'.($paslesmoyens?';background-color:#FF6666':'').'">';
			
			
			if ($row[2])
			{
				if ($paslesmoyens) echo '<span class=action_inactive>'.$row[0].' <br> '.($row[1]==1?1000:2000).'<img src="monnaie.png"></span>';
				else echo '<a class="atypespe" href="acheter.php?race='.$row[1].'">'.$row[0].' <br> '.($row[1]==1?1000:2000).'<img border=0 src="monnaie.png">';
				echo '<br><img border=0 style="width:150" onmouseover="description(\'inforace'.$row[1].'\');" onmouseout="cache();" class="vignettes" src=robes/vignettes/robe'.$row[3].'.png></a><br>';
				if ($paslesmoyens) echo '<span class=action_inactive>Pas assez d\'argent</span>'; else echo '<br>';
			}
			else
			{
				if ($paslesmoyens) echo '<span class=action_inactive>'.$row[0].' <br> <a class=atype5 href=achetepass.php>1 pass'.(accesAutorise('specialcouple')?'/couple':'').'</a></span>';
				else echo '<a class="atypespe" href="acheter.php?race='.$row[1].'">'.$row[0].' <br> 1 pass'.(accesAutorise('specialcouple')?'/couple':'');
				echo '<br><img border=0 style="width:150" onmouseover="description(\'inforace'.$row[1].'\');" onmouseout="cache();" class="vignettes" src=robes/vignettes/robe'.$row[3].'.png></a><br>';
				if ($paslesmoyens) echo '<a class=atype5 href=achetepass.php>Obtenir 1 pass</a>'; else echo '<br>';
			}
			echo '</td>';
		}
		
		echo '</tr></table>';
	}
		
    if (!isset($_SESSION['assoc']) or !$_SESSION['assoc'])
    {
    	echo '<br><span class=combleu>Races Spéciales</span>';
    	$query = "SELECT nom,raceid,envente,(SELECT robeid FROM robe WHERE disponible AND robe.raceid = race.raceid LIMIT 1) FROM race WHERE recherche = recherchetotal AND special = 0 AND envente = 0 ORDER BY raceid";
    	dispListRace(mysql_query($query));
    	echo '<span class=combleu>Races Normales</span>';
    	$query = "SELECT nom,raceid,envente,(SELECT robeid FROM robe WHERE disponible AND robe.raceid = race.raceid LIMIT 1) FROM race WHERE recherche = recherchetotal AND special = 0 AND envente ORDER BY raceid";
    	dispListRace(mysql_query($query));
    	
    }
    else
    {
    	$query = "SELECT nom,raceid,envente,(SELECT robeid FROM robe WHERE disponible AND robe.raceid = race.raceid LIMIT 1) FROM race WHERE recherche = recherchetotal AND special = 0 AND raceid = ".$_SESSION['raceassoc'].' ORDER BY envente DESC'; 
		dispListRace(mysql_query($query));
    }

	
	//$nbrace = mysql_num_rows($result);
	

	include "resumeraces.php";
}

echo finFenetre();
if (isset($_GET['erreur']))
{
	if ($_GET['erreur']==0)
		echo '<script language="javascript">alert("Vous avez bien acheté ce '.mot('animal').'.")</script>';
	else if ($_GET['erreur']==1)
		echo '<script language="javascript">alert("Ce '.mot('animal').' n\'est pas à vendre.")</script>';
	else if ($_GET['erreur']==2)
		echo '<script language="javascript">alert("Vous êtes déjà le propriétaire de ce '.mot('animal').'.")</script>';
	else if ($_GET['erreur']==3)
		echo '<script language="javascript">alert("Vous n\'avez pas assez d\'argent.")</script>';
	else if ($_GET['erreur']==4)
		echo '<script language="javascript">alert("Cette vente ne vous est pas réservée.")</script>';
	else if ($_GET['erreur']==5)
		echo '<script language="javascript">alert("'.$LASSOCIATION.' ne gère pas ces '.$ANIMAUX.'.")</script>';
}
include "finpage.php";
?>