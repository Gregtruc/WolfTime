<?php session_start(); include "connectdb.php";
include "alertefonctions.php";

//if (!isset($_SESSION['uid'])) { header('Location: index.php'); exit(); }

if (isset($_GET['gtid']) and possedeDroit(DROIT_SUPER_ADMIN))
{
	reload('?uid='.mysql_result(mysql_query('SELECT id FROM login WHERE gtid = '.$_GET['gtid'].' LIMIT 1'),0));
}
$result = mysql_query('SELECT compte,visite,anciennete,contrib,metier,comp1,comp2,comp3, lastconnect, maison, bday, bmonth, byear, reputation, atelier, argent, nbchevaux,avatar, description, association, monniveau, centre, ban,passach,ville,accomp1,accomp2,accomp3,ip,DATE_FORMAT(inscription,"%e") AS inscriptiond,DATE_FORMAT(inscription,"%c") AS inscriptionm,DATE_FORMAT(inscription,"%Y") AS inscriptiony,admin,myid,mascotte, pass,gtid, galop FROM login WHERE id = '.$_GET['uid']);
if (!mysql_num_rows($result)) { header('Location: index.php'); exit(); }
	
$joueur = mysql_fetch_array($result); 

$bloquage=0;
$result=mysql_query('SELECT niveau FROM bloquage WHERE uid1 = '.$_GET['uid'].' AND uid2 = '.$_SESSION['uid']);
if (mysql_num_rows($result))
	$bloquage=mysql_result($result,0);

$estmembre=0;
if ($joueur['association'] and mysql_num_rows(mysql_query('SELECT 1 FROM association WHERE aid = '.$_GET['uid'].' AND uid = '.$_SESSION['uid'].' LIMIT 1')))
	$estmembre=1;

include "constantes.php";
if (isset($_GET['occupe']) and possedeDroit(DROIT_VUE_MULTI_COMPTES))
{
	$uids=explode(';',$_GET['occupe']);
	foreach ($uids as $uid)
		foreach ($uids as $uid2)
			if ($uid!=$uid2)
				mysql_query('INSERT IGNORE INTO multicomptes_new(uid1, uid2, statut, admin) VALUES ('.$uid.','.$uid2.',1,'.$_SESSION['uid'].')');
	reload('?uid='.$_GET['uid']);
}
if (isset($_GET['exclure']) and possedeDroit(DROIT_GESTION_ASSOC))
{
	mysql_query('DELETE FROM association WHERE uid = '.$_GET['exclure'].' AND aid = '.$_GET['uid']);
	mysql_query('DELETE FROM autorise WHERE uid1 = '.$_GET['uid'].' AND uid2 = '.$_GET['exclure']);
	mysql_query('DELETE FROM surveille WHERE aid  = '.$_GET['uid'].' AND uid = '.$_GET['exclure']);
	mysql_query('DELETE surveille FROM surveille LEFT JOIN suggestions USING(sid) WHERE suggestions.aid = '.$_GET['uid'].' AND surveille.uid = '.$_GET['exclure']);
	
	reload('?uid='.$_GET['uid']);
}
if (isset($_GET['invalide']) and possedeDroit(DROIT_GESTION_ASSOC))
{
	mysql_query('UPDATE login SET validassoc=0 WHERE id = '.$_GET['uid']);
	mysql_query('DELETE FROM autorise WHERE uid1 = '.$_GET['uid']);
	
	reload('?uid='.$_GET['uid']);
}
if (isset($_GET['alerte']) and isset($_SESSION['uid']))
{
	ajouteEvenement('Anniversaire de {'.$joueur['compte'].'}',-2,$joueur['bday'],$joueur['bmonth']);
	header('Location:fiche.php?uid='.$_GET['uid']);
	exit();
}
if (isset($_GET['deletecom']) and gestionAutorisee())
{
	if (possedeDroit(DROIT_GESTION_FICHE))
		mysql_query('DELETE FROM comms WHERE comid = '.$_GET['deletecom']);
	else
		mysql_query('DELETE FROM comms WHERE comid = '.$_GET['deletecom'].' AND (destid = '.$_SESSION['uid'].' OR uid = '.$_SESSION['uid'].')');
	header('Location:fiche.php?uid='.$_GET['uid']);
	exit();
}
if (isset($_GET['don']) and gestionAutorisee() and is_numeric($_GET['don']) and isset($_SESSION['uid']) and $_SESSION['argent']>=$_GET['don'] and $_GET['don']>=500)
{
	if ($joueur['association'])
	{
			
		mysql_query('UPDATE login SET argent = argent - '.$_GET['don'].($estmembre?'':', don = don + 1').' WHERE id = '.$_SESSION['uid']);
		if (mysql_affected_rows())
		{
			mysql_query('UPDATE login SET argent = argent + '.$_GET['don'].($estmembre?'':', don = don + 1').' WHERE association = 1 AND id = '.$_GET['uid']);
			if (mysql_affected_rows())
				mysql_query('INSERT INTO infos(uid,message,type) VALUES('.$_GET['uid'].',"<a class=atype5 href=fiche.php?uid='.$_SESSION['uid'].'>'.$_SESSION['compte'].'</a> a fait un don de '.$_GET['don'].'<img src=monnaie.png>",7)');
		}
	}
	else if ($joueur['argent']<=0)
	{
		$_GET['don']=min($_GET['don'],floor((500-$joueur['argent'])/0.9));
		mysql_query('UPDATE login SET argent = argent - '.$_GET['don'].' WHERE id = '.$_SESSION['uid']);
		if (mysql_affected_rows())
		{
			mysql_query('UPDATE login SET argent = argent + '.floor(0.9*$_GET['don']).' WHERE id = '.$_GET['uid']);
			if (mysql_affected_rows())
				mysql_query('INSERT INTO infos(uid,message,type) VALUES('.$_GET['uid'].',"<a class=atype5 href=fiche.php?uid='.$_SESSION['uid'].'>'.$_SESSION['compte'].'</a> vous a fait un don de '.floor(0.9*$_GET['don']).'<img src=monnaie.png>",7)');
		}
	}
	header('Location:fiche.php?uid='.$_GET['uid']);
	exit();
}
if (isset($_GET['arrete']))
{
	mysql_query('DELETE FROM sponsors WHERE '.getday().'-depuis>=15 AND aid = '.$_GET['arrete'].' AND uid = '.$_SESSION['uid']);
	if (mysql_affected_rows())
		mysql_query('INSERT INTO infos(uid,message,type) VALUES('.$_GET['arrete'].',"<a class=atype5 href=fiche.php?uid='.$_SESSION['uid'].'>'.$_SESSION['compte'].'</a> a mis fin � son '.$SPONSORING.' '.$DELASSOCIATION.'",9)');
	header('Location:reglages.php?reglage=2');
	exit();
}
if (isset($_GET['offir']) and $_GET['uid']!=$_SESSION['uid'] and isset($_SESSION['evenement']) and $_SESSION['evenement']['type']==3 and isVeritableConnexion()) {
	$check_moi=mysql_query('SELECT * FROM evenement_'.$_SESSION['evenement']['nom'].' WHERE uid = '.$_SESSION['uid']);
	if (mysql_num_rows($check_moi)) // Si je participe
	{
		$evt_moi=mysql_fetch_assoc($check_moi);
		if ($evt_moi['points']>$evt_moi['pointsofferts']) // Si j'ai encore des points � offrir
		{
			$check_autre=mysql_query('SELECT IF(DATE_ADD(dernierrecu,INTERVAL '.$_SESSION['evenement']['frequence'].' MINUTE)<NOW(),1,0) AS dispo FROM evenement_'.$_SESSION['evenement']['nom'].' WHERE uid = '.$_GET['uid']);
			if (mysql_num_rows($check_autre)) // Si l'autre participe
			{
				$autre=mysql_fetch_assoc($check_autre);
				if ($autre['dispo']) // S'il n'a pas re�u de point depuis le temps autoris�
				{
					mysql_query('UPDATE evenement_'.$_SESSION['evenement']['nom'].' SET pointsofferts = pointsofferts + 1 WHERE uid = '.$_SESSION['uid'].' AND pointsofferts < points');
					if (mysql_affected_rows())
						mysql_query('UPDATE evenement_'.$_SESSION['evenement']['nom'].' SET dernierrecu = NOW(), pointsrecus = pointsrecus + 1 WHERE uid = '.$_GET['uid']);
				}
			}
		}
	}
}
if (!isset($_SESSION['uid']) or (isset($_SESSION['vraiuid']))) {} else
if (isset($_GET['action']) and isset($_SESSION['uid']))
{
	
	$action=$_GET['action'];
	$nom = mysql_result(mysql_query('SELECT compte FROM login WHERE id = '.$_GET['uid']),0);
	switch($action)
	{
	case 'amis':
		if (!$bloquage)
			mysql_query('UPDATE amis SET statut=1 WHERE uid1 = '.$_SESSION['uid'].' AND uid2 = '.$_GET['uid']);
		mysql_query('INSERT INTO infos(uid,message,type) VALUES('.$_GET['uid'].',"<a class=atype5 href=fiche.php?uid='.$_SESSION['uid'].'>'.$_SESSION['compte'].'</a> souhaite devenir votre ami.",3)');
		break;
	case 'validamis':
		if (mysql_result(mysql_query('SELECT statut FROM amis WHERE uid1 = '.$_GET['uid'].' AND uid2 = '.$_SESSION['uid'].' LIMIT 1'),0)==1)
		{
			mysql_query('UPDATE amis SET statut = 2 WHERE uid1 = '.$_GET['uid'].' AND uid2 = '.$_SESSION['uid']);
			mysql_query('INSERT INTO amis(uid1,uid2,nom2,statut) VALUES ("'.$_SESSION['uid'].'","'.$_GET['uid'].'","'.str_replace('"','\"',$nom).'",2) ON DUPLICATE KEY UPDATE statut = 2');
			mysql_query('INSERT INTO infos(uid,message,type) VALUES('.$_GET['uid'].',"<a class=atype5 href=fiche.php?uid='.$_SESSION['uid'].'>'.$_SESSION['compte'].'</a> accepte de devenir votre ami.",3)');
		}
		break;
	case 'refusamis':
		mysql_query('UPDATE amis SET statut=0 WHERE uid1 ='.$_GET['uid'].' AND uid2 = '.$_SESSION['uid'].' AND statut = 1');
		break;
	case 'stopamis':
		mysql_query('UPDATE amis SET statut=0 WHERE uid1 ='.$_SESSION['uid'].' AND uid2 = '.$_GET['uid'].' AND statut = 1');
		break;
	case 'suppamis':
		mysql_query('UPDATE amis SET statut=1 WHERE (uid1 ='.$_GET['uid'].' AND uid2 = '.$_SESSION['uid'].') OR (uid2 ='.$_GET['uid'].' AND uid1 = '.$_SESSION['uid'].')');
	case 'suppfavoris':
		$query = "DELETE FROM amis WHERE uid1 = ".$_SESSION['uid']." AND uid2 = ".$_GET['uid'];
		$result = mysql_query($query);
		break;
	case 'favoris':
		$query = "DELETE FROM amis WHERE uid1 = ".$_SESSION['uid']." AND uid2 = ".$_GET['uid'];
		$result = mysql_query($query);
		$query = 'INSERT INTO amis(uid1,uid2,nom2) VALUES ("'.$_SESSION['uid'].'","'.$_GET['uid'].'","'.str_replace('"','\"',$nom).'")';
		$result = mysql_query($query);
		//mysql_query('INSERT INTO infos(uid,message,type) VALUES('.$_GET['uid'].',"<a class=atype5 href=fiche.php?uid='.$_SESSION['uid'].'>'.$_SESSION['compte'].'</a> vous a ajout� comme ami.",3)');
		break;
	case 'autorise':
		mysql_query('INSERT INTO autorise(uid1,uid2,nom2) VALUES ("'.$_SESSION['uid'].'","'.$_GET['uid'].'","'.str_replace('"','\"',$nom).'")');
		break;
	case 'desautorise':
		$query = "DELETE FROM autorise WHERE uid1 = ".$_SESSION['uid']." AND uid2 = ".$_GET['uid'];
		$result = mysql_query($query);
		break;
	case 'bloque':
		if (!$joueur['admin'])
		{
			mysql_query("INSERT INTO bloquage(uid1,uid2) VALUES ('".$_SESSION['uid']."','".$_GET['uid']."')");
		}
		break;
	case 'debloque':
		mysql_query("DELETE FROM bloquage WHERE uid1 = ".$_SESSION['uid']." AND uid2 = ".$_GET['uid']);
		break;
	case 'bloquetout':
		mysql_query('UPDATE bloquage SET niveau = 2 WHERE uid1 = '.$_SESSION['uid'].' AND uid2 = '.$_GET['uid']);
		break;
	case 'debloquetout':
		mysql_query('UPDATE bloquage SET niveau = 1 WHERE uid1 = '.$_SESSION['uid'].' AND uid2 = '.$_GET['uid']);
		break;
	case 'feliciter':
		if (!isset($_SESSION['uid']) or (isset($_SESSION['vraiuid'])and !$_SESSION['assoc']) or ($joueur['ip']==$_SERVER['REMOTE_ADDR'] and !mysql_query('SELECT COUNT(1) FROM multicomptes_new WHERE uid1 = '.$_SESSION['uid'].' AND uid2='.$_GET['uid']))) {} else
		if (mysql_result(mysql_query('SELECT COUNT(1) FROM reputation WHERE uid = '.$_SESSION['uid'].' AND date > DATE_SUB(NOW(),INTERVAL 1 DAY)'),0)<$_SESSION['monniveau'] and $_SESSION['uid']!=$_GET['uid'] and mysql_result(mysql_query('SELECT COUNT(1) FROM reputation WHERE uid = '.$_SESSION['uid'].' AND did = '.$_GET['uid'].' AND bonus > 0 AND date > DATE_SUB(NOW(),INTERVAL 1 DAY)'),0)<2)
		{
			mysql_query('INSERT INTO reputation(uid,unom,did,dnom,bonus) VALUES ('.$_SESSION['uid'].',"'.$_SESSION['compte'].'",'.$_GET['uid'].',"'.$nom.'",1)');
			mysql_query('UPDATE login SET reputation = reputation + 1 WHERE id = '.$_GET['uid']);
			
			if (mysql_result(mysql_query('SELECT COUNT(1) FROM reputation WHERE uid = '.$_SESSION['uid'].' AND date > DATE_SUB(NOW(),INTERVAL 1 DAY)'),0)>$_SESSION['monniveau'] or mysql_result(mysql_query('SELECT COUNT(1) FROM reputation WHERE uid = '.$_SESSION['uid'].' AND did = '.$_GET['uid'].' AND bonus > 0 AND date > DATE_SUB(NOW(),INTERVAL 1 DAY)'),0)>2)
				mysql_query('UPDATE login SET reputation = reputation - 1 WHERE id = '.$_GET['uid']);
		}
		break;
	case 'blamer':
		if (!isset($_SESSION['uid']) or (isset($_SESSION['vraiuid'])and !$_SESSION['assoc']) or ($joueur['ip']==$_SERVER['REMOTE_ADDR'] and !mysql_query('SELECT COUNT(1) FROM multicomptes_new WHERE uid1 = '.$_SESSION['uid'].' AND uid2='.$_GET['uid']))) {} else
		if (!$bloquage and mysql_result(mysql_query('SELECT COUNT(1) FROM reputation WHERE uid = '.$_SESSION['uid'].' AND date > DATE_SUB(NOW(),INTERVAL 1 DAY)'),0)<$_SESSION['monniveau'] and $_SESSION['uid']!=$_GET['uid'] and mysql_result(mysql_query('SELECT COUNT(1) FROM reputation WHERE uid = '.$_SESSION['uid'].' AND did = '.$_GET['uid'].' AND bonus < 0 AND date > DATE_SUB(NOW(),INTERVAL 1 DAY)'),0)<2)
		{
			mysql_query('INSERT INTO reputation(uid,unom,did,dnom,bonus) VALUES ('.$_SESSION['uid'].',"'.$_SESSION['compte'].'",'.$_GET['uid'].',"'.$nom.'",-2)');
			mysql_query('UPDATE login SET reputation = reputation - 2 WHERE id = '.$_GET['uid']);
			
			if (mysql_result(mysql_query('SELECT COUNT(1) FROM reputation WHERE uid = '.$_SESSION['uid'].' AND date > DATE_SUB(NOW(),INTERVAL 1 DAY)'),0)>$_SESSION['monniveau'] or mysql_result(mysql_query('SELECT COUNT(1) FROM reputation WHERE uid = '.$_SESSION['uid'].' AND did = '.$_GET['uid'].' AND bonus < 0 AND date > DATE_SUB(NOW(),INTERVAL 1 DAY)'),0)<2)
				mysql_query('UPDATE login SET reputation = reputation + 2 WHERE id = '.$_GET['uid']);
		}
		break;
	case 'rose':
		if ($SITE_ID==2 and !isset($_SESSION['vraiuid']) and ($_GET['uid']!=$_SESSION['uid']) and accesAutorise('eventrose') and mysql_result(mysql_query('SELECT roserestant FROM chasse_stvalentin_2013 WHERE uid = '.$_SESSION['uid']),0) and !mysql_result(mysql_query('SELECT 1 FROM chasse_stvalentin_2013_roses WHERE date > DATE_SUB(NOW(), INTERVAL 5 HOUR) AND uid = '.$_SESSION['uid'].' AND destid = '.$_GET['uid']),0)) {
			mysql_query('UPDATE chasse_stvalentin_2013 SET roserestant = roserestant - 1, rosedon = rosedon +1 WHERE roserestant > 0 AND uid = '.$_SESSION['uid']);
			if (mysql_affected_rows()==1)
			{
				mysql_query('INSERT INTO chasse_stvalentin_2013_roses(uid,destid) VALUES ('.$_SESSION['uid'].','.$_GET['uid'].')');
			}
		}
		break;
	}
	
	// mise en cache de la liste des amis
	include "cacheamis.php";
	header('Location:fiche.php?uid='.$_GET['uid']);
}

include "header.php";
include "debutpage.php";
include "debutmenu.php";
include "menu.php";
include "finmenu.php";
include "debutaction.php";
echo debutAction();?>
<div class=centre>
<?php 
$nbanimaux=$joueur[16];
if ($joueur[17])
	echo '<img src="avatar/'.$_GET['uid'].'.avatar" style="width:100;height:100; border: 1px solid black">';
echo getTitle($_GET['uid']).'<br><br>';
if (possedeDroit(DROIT_VUE_MULTI_COMPTES) and !$joueur[19])
{
	$invalides=0;
	$list_multis=mysql_query('SELECT id,compte FROM login WHERE id!='.$_GET['uid'].' AND ipnum = '.ip2long($joueur[28]));
	if (mysql_num_rows($list_multis))
	{
		echo '<u><font color=orangered>M�me connexion</font></u><br>';
		$uids=$_GET['uid'];
		while ($multi=mysql_fetch_array($list_multis))
		{
			echo linkFiche($multi[1],$multi[0]);
			$check_multi=mysql_query('SELECT statut FROM multicomptes_new WHERE uid1 = '.$_GET['uid'].' AND uid2 = '.$multi['id']);
			if (mysql_num_rows($check_multi))
			{
				$multi_autorise=mysql_fetch_assoc($check_multi);
				if ($multi_autorise['statut']==2)
					echo '<img src=valide.png>';
				else echo '<img src=temps.gif>';
			}
			else {
				$invalides++;
				echo '<img src=pasvalide.png>';
			}
			echo '<br>';
			$uids.=';'.$multi[0];	
		}
		if ($invalides) echo '<a class=atype2 href=fiche.php?uid='.$_GET['uid'].'&occupe='.$uids.'>Je m\'en occupe</a><br>';
		echo '<a class=atype2 href=pmessage.php?uid='.$uids.'&multi>Avertir</a> - <a class=atype2 href=multiautorise.php?uid='.$_GET['uid'].'>Autoriser</a><br><br>';
	}
}
echo '<u>En r�sum� :</u><br>';
echo 'niveau '.$joueur['monniveau'].'<br>';
echo mot('galop').' '.vraiGalop($joueur['galop']).'<br>';
echo '<a class=atype7 href=elevage.php?uid='.$_GET['uid'].'>'.$joueur[16].' '.mot('animal','',0,$joueur[16]>1?1:0).'</a> <br>';
echo formatNombre($joueur[15]).'<img src="monnaie.png"><br>';
//echo 'Chevaux : '.'<br>';
echo '<a class=atype5 href=statsavances.php?uid='.$_GET['uid'].'>Stats avanc�es</a><br>';

if (possedeDroit(DROIT_SUPER_ADMIN))
{
	echo '<br /><u>Superadmin :</u><br />';
	echo 'GT : '.$joueur['gtid'].'<br />';
	echo $joueur[23].' pass ach.<br>';
	echo $joueur['pass'].' pass stck<br />';
}
echo '<br>';
/*
$reputation_niveau=array(-40,-30,-20,-10,-1,0,10,20,30,40,50,75,100,250,200,250,300,350,400,450,500,10000);
$reputation_titre=array('Bandit de petit chemin','Mal-aim�','D�sagr�able','Mal poli','Peu sympa','Neutre','Sympa','Gentil','Gentleman','Serviable','D�vou�','Fid�le','Juste',
		'Populaire','C�l�bre','Envi�','Ador�','Chouchou','Idole','V�n�r�','Adul�','Superstar');

for( $i=0;isset($reputation_niveau[$i]);$i++)
	if ($joueur[13]<=$reputation_niveau[$i]) break;

$reput=$i;
$list_reputations='';
for( $i=0;isset($reputation_niveau[$i]);$i++)
{
	
	$list_reputations.='<font color='.(($reputation_niveau[$i]<0)?'orangered':'blue').'>';
	if ($i==$reput)
		$list_reputations.='<b>';
	$list_reputations.=$reputation_titre[$i];
	if ($i==$reput)
		$list_reputations.='</b>';
	$list_reputations.='</font><br>';
}*/
//echo '<u>La r�putation de <nobr>'.$joueur[0].' :</nobr></u> <img src=icones/info.png onmouseover="montre(\''.$list_reputations.'\')" onmouseout="cache();"><br>';
//echo ($joueur[13]>0?'+':'').$joueur[13].'<br>';
/*echo '<font face="Comic Sans MS" color="'.($joueur[13]>=0?'#0000DD':'orangered').'">';

echo $reputation_titre[$reput];
echo '</font><br>';
if (!isset($_SESSION['uid']) or (isset($_SESSION['vraiuid']))) {} else
if (isset($_SESSION['uid']))
{
	if ($_SESSION['uid']==$_GET['uid'] or ($joueur['ip']==$_SERVER['REMOTE_ADDR'] and !mysql_num_rows(mysql_query('SELECT 1 FROM multicomptes WHERE uid = '.$_SESSION['uid'].' AND INSTR(autres,"'.$_GET['uid'].'") LIMIT 1'))) or $bloquage)
	{}
	else if (mysql_result(mysql_query('SELECT COUNT(1) FROM reputation WHERE uid = '.$_SESSION['uid'].' AND date > DATE_SUB(NOW(),INTERVAL 1 DAY)'),0)>=$_SESSION['monniveau'])
		echo '<br>Vous ne pouvez plus f�liciter ni bl�mer personne pour le moment.<br>';
	else if (mysql_result(mysql_query('SELECT COUNT(1) FROM reputation WHERE uid = '.$_SESSION['uid'].' AND did = '.$_GET['uid'].' AND date > DATE_SUB(NOW(),INTERVAL 1 DAY)'),0)>=2)
		echo '<br>Vous ne pouvez plus f�liciter ni bl�mer '.$joueur[0].' pour le moment.<br>';
	else
	{
		echo '<br>';
		
		echo '<a class="atype5" href="fiche.php?uid='.$_GET['uid'].'&action=feliciter">F�liciter '.$joueur[0].'</a><br>';
		echo '<a class="atype5" href="fiche.php?uid='.$_GET['uid'].'&action=blamer">Bl�mer '.$joueur[0].'</a><br>';
	}
}*/
	
echo makeJaime($_GET['uid'],1);

echo '<br>';

if (isset($_SESSION['uid']) and $_GET['uid']!=$_SESSION['uid']and isset($_SESSION['evenement']) and $_SESSION['evenement']['type']==3 and isVeritableConnexion())
{
	$check_moi=mysql_query('SELECT * FROM evenement_'.$_SESSION['evenement']['nom'].' WHERE uid = '.$_SESSION['uid']);
	if (mysql_num_rows($check_moi)) // Si je participe
	{
		$evt_moi=mysql_fetch_assoc($check_moi);
		if ($evt_moi['points']>$evt_moi['pointsofferts']) // Si j'ai encore des points � offrir
		{
			$check_autre=mysql_query('SELECT IF(DATE_ADD(dernierrecu,INTERVAL '.$_SESSION['evenement']['frequence'].' MINUTE)<NOW(),1,0) AS dispo FROM evenement_'.$_SESSION['evenement']['nom'].' WHERE uid = '.$_GET['uid']);
			if (mysql_num_rows($check_autre)) // Si l'autre participe
			{
				$autre=mysql_fetch_assoc($check_autre);
				if ($autre['dispo']) // S'il n'a pas re�u de point depuis le temps autoris�
					echo '<a class=atype5 href=fiche.php?uid='.$_GET['uid'].'&offir=1>Offrir 1 '.$_SESSION['evenement']['nomobjet'].'</a>';
				else
					echo '<span class=action_inactive>Offrir 1 '.$_SESSION['evenement']['nomobjet'].' (Attente)</span>';
			}
		} else
			echo '<span class=action_inactive>Offrir 1 '.$_SESSION['evenement']['nomobjet'].'</span>';
		echo '<br><br>';
	}
	
}
$r2=mysql_query('SELECT aid,anom FROM association WHERE uid = '.$_GET['uid'].' AND valide');
if (mysql_num_rows($r2))
{
	echo '<u>Les '.$ASSOCIATIONS.' de <nobr>'.$joueur[0].' :</nobr></u><br>';
	while ($joueurtoto=mysql_fetch_row($r2))
		echo '<a class="atype10" href="fiche.php?uid='.$joueurtoto[0].'">'.$joueurtoto[1].'</a><br>';
	echo '<br>';
}
if ($joueur[19])
{
	echo '<u>Les membres de <nobr>'.$joueur[0].' :</nobr></u><br>';
	$result=mysql_query('SELECT uid, unom FROM association WHERE valide > 0 AND aid = '.$_GET['uid']);
	while ($joueurtoto=mysql_fetch_row($result))
		echo (possedeDroit(DROIT_GESTION_ASSOC)?'<a href=fiche.php?uid='.$_GET['uid'].'&exclure='.$joueurtoto[0].' onclick="return confirm(\'Vous allez exclure ce joueur de cette association.\');"><img src=icones/pasvalide.png></a> ':'').linkFiche($joueurtoto[1],$joueurtoto[0]).'<br>';
	echo '<br>';

	$result=mysql_query('SELECT uid,compte FROM sponsors LEFT JOIN login ON uid=id WHERE aid = '.$_GET['uid']);
	if (mysql_num_rows($result))
	{
		echo '<u>Les sponsors de <nobr>'.$joueur[0].' :</nobr></u><br>';
		mysql_query('SELECT uid, unom FROM association WHERE valide > 0 AND aid = '.$_GET['uid']);
		while ($joueurtoto=mysql_fetch_row($result))
			echo linkFiche($joueurtoto[1],$joueurtoto[0]).'<br>';
		echo '<br>';
	}
}	

$result = mysql_query("SELECT uid2,nom2 FROM amis WHERE uid1 = ".$_GET['uid'].' AND statut = 2 LIMIT 25');
if (mysql_num_rows($result) or mysql_num_rows($result2))
{
	if (mysql_num_rows($result)) {
		$fin = '';
		if (mysql_num_rows($result)>=25)
			$fin= '<a class=atype7 href=listeamis.php?uid='.$_GET['uid'].'>etc...</a>';
		echo '<a class=atype7 href=listeamis.php?uid='.$_GET['uid'].'>Les amis de <nobr>'.$joueur[0].' :</nobr></a><br><div class=amis>';
		while($joueur2 = mysql_fetch_row($result))
			echo linkFiche($joueur2[1],$joueur2[0]).'<br>';
		echo $fin;
		echo '</div><br>';
	}
}
?></div>
<?php 
echo finAction();echo debutAction();
if ($joueur['myid']){
	$son_mycompte=mysql_fetch_assoc(mysql_query('SELECT * FROM commun.comptes WHERE myid = '.$joueur['myid']));
	
	for ($i=1;$i<=3;$i++)
	{
		if ($i!=$SITE_ID and $son_mycompte['uid'.$i])
		{
			
			$mycompte=$son_mycompte;
			echo '<u>'.$SITES_NOM[$i].'</u><br><a class="atype5" href="http://www.'.$SITES_NOMSIMPLE[$i].'.fr/fiche.php?uid='.$mycompte['uid'.$i].'">'.$mycompte['compte'.$i].'</a><br>niveau '.$mycompte['niveau'.$i].'<br>'.$mycompte['nbanimaux'.$i].' '.($mycompte['nbanimaux'.$i]>1?'animaux':'animal').'<br>'.formatNombre($mycompte['argent'.$i]).' <img src=http://www.'.$SITES_NOMSIMPLE[$i].'.fr/monnaie.png>';
			echo finAction();echo debutAction();
		}
	}
}
include "pub.php";
echo finAction();
include "finaction.php";

echo debutSousTitre();
echo afficheSousTitre($joueur[0]);
echo '<center>';
include "accompnom.php";
if ($joueur[25] or $joueur[26] or $joueur[27])
	echo '<br>';
if ($joueur[25])
	echo '<nobr><span class=accomp>'.$ACCOMP_NOM[$joueur[25]].'</span></nobr>';
if ($joueur[26] and $joueur[25])
	echo ' ; ';
if ($joueur[26])
	echo '<nobr><span class=accomp>'.$ACCOMP_NOM[$joueur[26]].'</span></nobr>';
if ($joueur[27] and ($joueur[26] or $joueur[25]))
	echo ' ; ';
if ($joueur[27])
 	echo '<nobr><span class=accomp>'.$ACCOMP_NOM[$joueur[27]].'</span></nobr>';
?>
<?if (isset($_SESSION['admin']) and $_SESSION['admin']):?>
<br>
<? if ($_GET['uid']!=$_SESSION['uid']):?>
<a class=atype5 href=recompenses.php?uid=<?=$_GET['uid']?>>Donner des accomplissements</a> -
<?php endif;?> 
<a class="atype5" href="histovente.php?uid=<?=$_GET['uid']?>">Achats/ventes</a> - <a class="atype5" href="histoinfos.php?uid=<?=$_GET['uid']?>">Voir son historique</a> - <a class="atype5" href="bannir.php?uid=<?=$_GET['uid']?>">Sanctions</a>
<? if (possedeDroit(DROIT_VUE_IP)):?>
- <a class=atype5 href="histoconnexion.php?uid=<?=$_GET['uid']?>">Connexions</a> 
- <a class=atype5 href="mdpchange.php?uid=<?=$_GET['uid']?>">Changer le MDP</a>
<? endif; ?>
<?php if (possedeDroit(DROIT_PANNEAU_ADMIN)):?>
- <a class="atype5" href="passutilises.php?uid=<?=$_GET['uid']?>">Objets pass achet�s</a>
<?php endif;?>
<br>
<?endif;
echo '</center>';
?>

<br><br>

<?php 
$nomMetiers=$LISTE_METIERS;
$is_ami=0;
$mois= array('janvier', 'f�vrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'ao�t', 'septembre', 'octobre', 'novembre', 'd�cembre');

echo '<div style="float:right; text-align:right;">';
if (isset($_SESSION['uid']))
{
	if ($SITE_ID==2 and !isset($_SESSION['vraiuid']) and ($_GET['uid']!=$_SESSION['uid']) and accesAutorise('eventrose') and mysql_result(mysql_query('SELECT roserestant FROM chasse_stvalentin_2013 WHERE uid = '.$_SESSION['uid']),0))
		echo '<a onclick="return confirm(\'Offrir une rose � ce joueur?\')" onmouseover="montre(\'Offrir une rose � ce joueur\');" onmouseout="cache();" href="fiche.php?uid='.$_GET['uid'].'&action=rose"><img src=evenement/rose.png width=40 border=0></a>';
	
	$is_ami=0;
	$amitie=mysql_query('SELECT statut FROM amis WHERE uid1 = '.$_GET['uid'].' and uid2 = '.$_SESSION['uid'].' LIMIT 1');
	$amitie2=mysql_query('SELECT statut FROM amis WHERE uid1 = '.$_SESSION['uid'].' and uid2 = '.$_GET['uid'].' LIMIT 1');
	$amitie_statut=-1;
	$amitie_statut2=-1;
	if (mysql_num_rows($amitie))
	{
		$amitie_statut=mysql_result($amitie,0);
		$is_ami=($amitie_statut==2)?1:0;
	}
	if (mysql_num_rows($amitie2))
	{
		$amitie_statut2=mysql_result($amitie2,0);
	}
	
	if  ($_SESSION['admin'] or $is_ami)
	{
		echo '<a onmouseover="montre(\'Attirer son attention sur quelque chose\');" onmouseout="cache();" href="attention.php?uid='.$_GET['uid'].'"><img src=icones/attention.png border=0></a>';
	}
	if (!isset($_SESSION['vraiuid']))
	{
		if (mysql_result(mysql_query('SELECT COUNT(1) FROM autorise WHERE uid2 = '.$_SESSION['uid'].' and uid1 = '.$_GET['uid']),0))
			echo '<a onmouseover="montre(\'G�rer son compte\');" onmouseout="cache();" href="switchto.php?uid='.$_GET['uid'].'"><img src=icones/gere.png border=0></a>';
		if (mysql_result(mysql_query('SELECT COUNT(1) FROM autorise WHERE uid1 = '.$_SESSION['uid'].' and uid2 = '.$_GET['uid']),0))
		{
			echo '<a onclick="return confirm(\'Etes vous s�r de vouloir interdire ce joueur de g�rer votre compte?\')" onmouseover="montre(\'Interdire ce joueur de g�rer mon compte\');" onmouseout="cache();" href="fiche.php?uid='.$_GET['uid'].'&action=desautorise"><img src=icones/desautorise.png border=0></a>';
		}
		else if ($is_ami)
			echo '<a onclick="return confirm(\'Etes vous s�r de vouloir autoriser ce joueur � g�rer votre compte?\')" onmouseover="montre(\'Autoriser � g�rer mon compte\');" onmouseout="cache();" href="fiche.php?uid='.$_GET['uid'].'&action=autorise&nom='.$joueur[0].'"><img src=icones/autorise.png border=0></a>';
		
		if ($is_ami)
		{
			
			echo '<a onmouseover="montre(\'Retirer de mes amis\');" onmouseout="cache();" href="fiche.php?uid='.$_GET['uid'].'&action=suppamis"><img src=icones/pamis.png border=0></a>';
		}
		else if ($amitie_statut==1)
		{
			echo '<a onmouseover="montre(\'Accepter de devenir ami\');" onmouseout="cache();" href="fiche.php?uid='.$_GET['uid'].'&action=validamis&nom='.$joueur[0].'"><img src=icones/amis.png border=0></a>';
			echo '<a onmouseover="montre(\'Refuser de devenir amis\');" onmouseout="cache();" href="fiche.php?uid='.$_GET['uid'].'&action=refusamis"><img src=icones/pamis.png border=0></a>';
		}
		else if ($amitie_statut2==1)
		{
			echo '<a onmouseover="montre(\'Annuler ma demande de devenir amis\');" onmouseout="cache();" href="fiche.php?uid='.$_GET['uid'].'&action=stopamis"><img src=icones/pamis.png border=0></a>';
		}
		else if ($amitie_statut2==0)
		{
			if (!$bloquage)
				echo '<a onmouseover="montre(\'Proposer de devenir amis\');" onmouseout="cache();" href="fiche.php?uid='.$_GET['uid'].'&action=amis&nom='.$joueur[0].'"><img src=icones/amis.png border=0></a>';
			echo '<a onmouseover="montre(\'Retirer de mes favoris\');" onmouseout="cache();" href="fiche.php?uid='.$_GET['uid'].'&action=suppfavoris"><img src=icones/pasfavoris.png border=0></a>';
		}
		else if (!mysql_result(mysql_query('SELECT COUNT(1) FROM bloquage WHERE uid1 = '.$_SESSION['uid'].' and uid2 = '.$_GET['uid']),0))
		{
			echo '<a onmouseover="montre(\'Ajouter � mes favoris\');" onmouseout="cache();" href="fiche.php?uid='.$_GET['uid'].'&action=favoris&nom='.$joueur[0].'"><img src=icones/favoris.png border=0></a>';
			if (!$joueur['admin'])
				echo '<a onmouseover="montre(\'Bloquer\');" onmouseout="cache();" href="fiche.php?uid='.$_GET['uid'].'&action=bloque"><img src=icones/bloque.png border=0></a>';
		}
		else if (mysql_result(mysql_query('SELECT COUNT(1) FROM bloquage WHERE niveau = 1 AND uid1 = '.$_SESSION['uid'].' and uid2 = '.$_GET['uid']),0))
		{
			echo '<a onmouseover="montre(\'D�bloquer\');" onmouseout="cache();" href="fiche.php?uid='.$_GET['uid'].'&action=debloque"><img src=icones/debloque.png border=0></a>';
			echo '<a onmouseover="montre(\'Bloquer tout\');" onmouseout="cache();" href="fiche.php?uid='.$_GET['uid'].'&action=bloquetout"><img src=icones/bloquetout.png border=0></a>';
		}
		else
		{
			echo '<a onmouseover="montre(\'D�bloquer sauf message\');" onmouseout="cache();" href="fiche.php?uid='.$_GET['uid'].'&action=debloquetout"><img src=icones/debloquetout.png border=0></a>';
		}
		
		
		/*
		echo '<a onmouseover="montre(\'Retirer mes amis\');" onmouseout="cache();" href="fiche.php?uid='.$_GET['uid'].'&action=suppami"><img src=pamis.png border=0></a>';
		if (!mysql_result(mysql_query('SELECT 1 FROM autorise WHERE uid1 = '.$_SESSION['uid'].' and uid2 = '.$_GET['uid']),0))
			echo '<a onclick="return confirm(\'Etes vous s�r de vouloir autoriser ce joueur � g�rer votre compte?\')" onmouseover="montre(\'Autoriser � g�rer mon compte\');" onmouseout="cache();" href="fiche.php?uid='.$_GET['uid'].'&action=autorise&nom='.$joueur[0].'"><img src=autorise.png border=0></a>';
		else
			echo '<a onclick="return confirm(\'Etes vous s�r de vouloir interdire ce joueur de g�rer votre compte?\')" onmouseover="montre(\'Interdire ce joueur de g�rer mon compte\');" onmouseout="cache();" href="fiche.php?uid='.$_GET['uid'].'&action=desautorise"><img src=desautorise.png border=0></a>';
		if (mysql_result(mysql_query('SELECT 1 FROM autorise WHERE uid2 = '.$_SESSION['uid'].' and uid1 = '.$_GET['uid']),0))
			echo '<a onmouseover="montre(\'G�rer son compte\');" onmouseout="cache();" href="switchto.php?uid='.$_GET['uid'].'"><img src=gere.png border=0></a>';
		*/
	}
	/*if (!isset($_SESSION['vraiuid']) and !mysql_result(mysql_query('SELECT 1 FROM bloquage WHERE uid1 = '.$_SESSION['uid'].' and uid2 = '.$_GET['uid']),0))
		echo '<a onmouseover="montre(\'Bloquer\');" onmouseout="cache();" href="fiche.php?uid='.$_GET['uid'].'&action=bloque"><img src=bloque.png border=0></a> <br>';
	else if (!isset($_SESSION['vraiuid'])) echo '<a onmouseover="montre(\'D�bloquer\');" onmouseout="cache();" href="fiche.php?uid='.$_GET['uid'].'&action=debloque"><img src=debloque.png border=0></a> <br>';
	*/
	if (!$bloquage)
		echo '<a onmouseover="montre(\'Envoyer un message\');" onmouseout="cache();" href="pmessage.php?uid='.$_GET['uid'].'"><img src=icones/message.png border=0></a>';		
	
		echo '<br><br>';
	if ($joueur[14])
		{
			echo '<a onmouseover="montre(\'Voir son atelier\');" onmouseout="cache();" href="atelier.php?uid='.$_GET['uid'].'"><img width=40 src=icones/atelier.png border=0></a>';
		}
	if ($joueur[21])
		{
			echo '<a onmouseover="montre(\'Voir son centre\');" onmouseout="cache();" href="vuecentre.php?uid='.$_GET['uid'].'"><img width=40 src=icones/centre.png border=0></a>';
		}
	echo '<a onmouseover="montre(\'Voir son classement\');" onmouseout="cache();" href="classement.php?uid='.$_GET['uid'].'"><img width=40 src=icones/classement.png border=0></a>';
	echo '<br>';		
}
echo '</div>';
echo '<span class="combleu">Anciennet� :</span> '.$joueur[2].' jours<br>';
echo '<span class="combleu">Derni�re connexion :</span> ';
getday();
if ($_SESSION['day']-$joueur[1]==0 and $joueur[8] >= date('H\hi',time()-120))
	echo 'En ligne ( Vu � '.$joueur[8].' )';
else if ($_SESSION['day']-$joueur[1]==0)
	echo 'Aujourd\'hui ( Vu � '.$joueur[8].' )';
else if ($_SESSION['day']-$joueur[1]==1)
	echo 'Hier ( Vu � '.$joueur[8].' )';
else if (!$joueur[1])
	echo 'Jamais';
else
	echo 'Il y a '.($_SESSION['day']-$joueur[1]).' jours';
echo '<br>';
if ($joueur[4])
	echo '<span class="combleu">M�tier :</span> '.$nomMetiers[$joueur[4]-1].' ('.$joueur[5].'% - '.$joueur[6].'% - '.$joueur[7].'%)<br>';

if ($joueur[10] and $joueur[11])
{
	echo '<span class="combleu">Anniversaire :</span> ';
	echo 'le '.$joueur[10].' '.$mois[$joueur[11]-1];
	if (isset($_SESSION['uid']) and $_SESSION['admin']>=32) echo ' '.$joueur[12];
	echo ' <a href=fiche.php?uid='.$_GET['uid'].'&alerte=1 onclick="return confirm(\'Ajouter � vos alertes?\');"><img src=icones/cloche.png border=0></a>';
}
echo '<br><span class="combleu">Inscription : </span> le '.$joueur[29].' '.$mois[$joueur[30]-1].' '.$joueur[31];
echo '<br>';
echo finSousTitre();
echo debutFenetre();
//$res = mysql_query('SELECT description FROM login WHERE id = '.$_GET['uid']);
//$descrtext = mysql_result($res,0);
include "smileys.php";
if ($joueur[19])
{
	$associ=mysql_fetch_row(mysql_query('SELECT nomassoc, raceassoc, (SELECT nom FROM race WHERE raceid = raceassoc),objassoc,validassoc,visite FROM login WHERE association AND id = '.$_GET['uid']));

	echo '<center><span class="combleu" style="font-size:18px">'.$ASSOCIATIONMAJ.' : ';
	echo mysql_result(mysql_query('SELECT nomassoc FROM login WHERE association AND id = '.$_GET['uid']),0);
	echo '</span></center>';
	echo '<div class="historique">';
	echo '<span class="combleu">Objectif '.$DELASSOCIATION.' :</span><br>';
	echo replaceSmiley($associ[3]).'<br>';
	
	echo '<br><span class="combleu">Race '.$DELASSOCIATION.' :</span> <a class="atype6" href="inforace.php?race='.$associ[1].'">'.$associ[2].'</a><br>';
	
	echo '<br><span class="combleu">Type de pouvoir :</span><br>Anarchique, chacun est libre de faire ce qu\'il veut.<br>';
		
	echo '<br><span class="combleu">Statut '.$DELASSOCIATION.' : </span>';
	
	if (!$associ[4])
		echo 'En construction<br>';
	else if ($associ[4]==1)
		echo 'En attente de validation<br>';
	else if ($associ[4]==2)
		echo 'Valid�'.(genre('association')==2?'e':'').(possedeDroit(DROIT_GESTION_ASSOC)?' <a href=fiche.php?uid='.$_GET['uid'].'&invalide=1 onclick="return confirm(\'Vous allez invalider cette association.\');"><img src=icones/pasvalide.png></a>':'').'<br>';
		
	if (isset($_SESSION['uid']) and mysql_result(mysql_query('SELECT COUNT(1) FROM association WHERE valide = 0 AND uid = '.$_SESSION['uid'].' AND aid = '.$_GET['uid']),0) and !isset($_SESSION['vraiuid']))
	{
		echo '<br><span style="float:right"><a class=atype10 href="association.php?uid='.$_GET['uid'].'&join=1">Accepter</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a class=atype9 href="association.php?uid='.$_GET['uid'].'&refuse=1">Refuser</a></span>
					<span class="combleu">Rejoindre '.$LASSOCIATION.' : </span>';
		echo '<br>';
	}
	
	if (isset($_SESSION['uid']) and $associ[4]==2 and $_SESSION['anciennete']>=10 and !isset($_SESSION['vraiuid']))
	{
		echo '<br><form method=get onsubmit="return confirm(\'Vous allez donner de l\\\'argent de votre r�serve personnelle.\');"><input type="hidden" name="uid" value="'.$_GET['uid'].'"><span class="combleu">Faire un don (minimum 500<img src=icones/monnaie.png>) : </span> <input type="text" style="width:50" name="don" value="500"> <input type="submit" value="Donner"></form>';
	}
	if (isset($_SESSION['uid']) and $associ[4]==2 and $_SESSION['monniveau']>=15 and !isset($_SESSION['vraiuid']) and mysql_result(mysql_query('SELECT sponsor FROM reglages_assoc WHERE aid = '.$_GET['uid']),0))
	{
		$est_sponsor=mysql_query('SELECT * FROM sponsors WHERE aid = '.$_GET['uid'].' AND uid = '.$_SESSION['uid']);
		if ($detail=mysql_fetch_row($est_sponsor))
		{
			echo '<br><span class="combleu">'.$SPONSORMAJ.' : </span> Votre '.$SPONSORING.' s\'�l�ve � '.$detail[3].'<img src=monnaie.png> par jour d\'activit�. Vous �tes '.$SPONSOR.' depuis ';
			if (getday()-$detail[2]==0)
				echo 'aujourd\'hui';
			else if (getday()-$detail[2]==1)
				echo 'hier';
			else
			    echo (getday()-$detail[2]).' jours';	
			echo '. '.(getday()-$detail[2]>=15?'<a class=atype2 href=reglages.php?arrete='.$detail[1].'>Arr�ter le '.$SPONSORING.'</a>':'').'<br>';
		}
		else
			echo '<br><a class=atype5 href="sponsor.php?aid='.$_GET['uid'].'" style="float:right">Devenir '.$SPONSOR.' '.$DELASSOCIATION.'</a><span class="combleu">'.$SPONSORMAJ.' : </span>';
	}
	
	echo '</div>';
	echo '<br>';

}
if ($_SESSION['monniveau']>=6 and $_GET['uid']!=$_SESSION['uid'] and !$joueur['association'] and $joueur['argent']<=0)
{
	echo '<form method=get onsubmit="return confirm(\'Vous allez donner de l\\\'argent de votre r�serve personnelle.\');"><input type="hidden" name="uid" value="'.$_GET['uid'].'"><span class=combleu>Aider ce joueur</span><br>Vous pouvez effectuer un don � ce joueur : <input type=text name=don value=0 style="width:50px"><img src=icones/monnaie.png> <input type=submit value="Donner"> (Entre 500 <img src=icones/monnaie.png> et '.floor((500-$joueur['argent'])/0.9).'<img src=icones/monnaie.png>)<br><img src=attention.png> <span class=attention>Une taxe de 10% sera pr�lev�e sur le don.</span></form><br>';
}
if (strcmp($joueur[18],"") and !($joueur[22]&16))
{
	echo '<span style="float:right">'.makeJaime($_GET['uid'],2).'</span><span class="combleu">La description de '.$joueur[0].' :</span><br>'; //<table cellspacing=0 cellpadding=0 style="width:610;overflow:hidden"><tr><td>
	echo '<div class="historique" '.((isset($_SESSION['fichestyle']) and $_SESSION['fichestyle'])?'style="width:620;height:400;overflow-y:scroll;overflow-x:hidden"':'').'>';
	echo replaceSmiley($joueur[18]);//</td></tr></table>
	echo '</div>';
	echo '<br><br>';
}

if ($joueur['mascotte'] and $joueur['monniveau']>=3 and accesAutorise('mascotte') and mysql_result(mysql_query('SELECT COUNT(1) FROM mascotte_objets WHERE uid = '.$_GET['uid'].' AND type = 1 AND categorie = 0'),0))
{
	$list_articles=mysql_query('SELECT * FROM mascotte_objets WHERE uid = '.$_GET['uid'].' AND utilise = 1 ORDER BY profondeur');
	if (mysql_num_rows($list_articles)) {
	echo '<span style="float:right">'.makeJaime($_GET['uid'],3).'</span><span class=combleu>La mascotte de '.$joueur[0].' :</span><br>';
	echo '<div style="width:600px;height:400px;position:relative;margin:auto;overflow:hidden;" >';
	
	
	while ($article=mysql_fetch_assoc($list_articles))
	{
		echo '<img id="objet'.$article['maid'].'" src=mascotte/article'.$article['maid'].'.png  style="position:absolute;top:'.$article['positiony'].'px;left:'.$article['positionx'].'px;z-index:'.$article['profondeur'].';width:'.$article['taille'].'px;'.($article['angle']?'transform:rotate('.$article['angle'].'deg);':'').'">';
	}
	
	echo '<img src=pixel.png style="width:600px;height:400px;position:absolute;top:0px;left:0px;z-index:256">';
	echo '</div>';
	echo '<br><br>';
	}
}

echo /*<a class="atype5" style="float:right" href="commentaire.php?sid=-'.$_GET['uid'].'">Voir tout</a>*/'<span class="combleu">Les commentaires � '.$joueur[0].' :</span><br>';
echo '<div class="historique" '.((isset($_SESSION['fichestyle']) and $_SESSION['fichestyle'])?'style="width:620;height:250;overflow-y:scroll;overflow-x:hidden"':'').'>';

$query = 'SELECT * FROM comms WHERE destid = '.$_GET['uid'].' ORDER BY comid DESC LIMIT 10';
$resultcom = mysql_query($query);

while ($commentaire=mysql_fetch_row($resultcom))
{
	if (isset($_SESSION['uid']) and !isset($_SESSION['vraiuid']) and ($_SESSION['uid']==$_GET['uid'] or $_SESSION['uid']==$commentaire[2] or possedeDroit(DROIT_GESTION_FICHE)))
		echo '<span style=float:right><a href="fiche.php?uid='.$_GET['uid'].'&deletecom='.$commentaire[0].'"><img border=0 src="pasvalide.png"></a></span>';
	echo '<span class="comms">De '.linkFiche($commentaire[3],$commentaire[2]).' le <font face="Comic Sans MS" color="#248884">'.$commentaire[5].'</font></span><br>';	
	echo replaceSmiley($commentaire[4]);
	echo '<br><br>';
}
echo '</div>';

$bloque_comms=mysql_result(mysql_query('SELECT commentaire FROM reglages WHERE id = '.$_GET['uid']),0);
if (gestionAutorisee() and !($_SESSION['ban']&4) and (!$bloque_comms or $is_ami or $_GET['uid']==$_SESSION['uid']))
{
	echo '<form method="POST" action="commentfiche.php" name="comform">';
	echo '<a class=atype5 style="float:right" href="commentaires.php?uid='.$_GET['uid'].'">Voir tous les commentaires</a>';
	$compteurid='compteur';
	include 'outiltexte.php';
	echo '<textarea class="formulaires" rows=2 name="commentaire" id=texte onkeyup="majCompteur(\'texte\',\'compteur\');" placeholder="Entrez ici votre commentaire"></textarea><br>
<input type="image" src="envoyer.png">
<input type="hidden" value="-'.$_GET['uid'].'" name="sid">
</form>';
}
echo '<br><br>';


echo '<span class="combleu">Activit� de '.$joueur[0].' sur la place publique et le coin des jeux :</span> <br>';
echo '<a class="atype5" href="listemessages.php?uid='.$_GET['uid'].'">Voir tous les sujets cr��s sur la place publique</a><br>';
echo '<a class="atype5" href="listejdr.php?uid='.$_GET['uid'].'">Voir ses inscriptions aux jeux de r�le</a><br>';
echo '<br>';
//<table cellspacing=0 cellpadding=0 style="width:610;overflow:hidden"><tr><td>
if ($joueur[9])
{
echo '<span class="combleu">Le voisinage de '.$joueur[0].' (<a class="combleu" href="cartecomplete.php?ville='.$joueur[24].'">'.$LISTE_VILLES[$joueur[24]].'</a>):</span> ';
drawPlateau(3,$joueur[9],0,$joueur[24]);
}

echo '<a class="atype5" style="float:right" href="tableau.php?uid='.$_GET['uid'].'">Voir</a>';
echo '<span class="combleu">Le tableau des robes rencontr�es par '.$joueur[0].'</span><br><br>';
echo '<a class="atype5" style="float:right" href="noteelevage.php?uid='.$_GET['uid'].'">Voir</a>';
echo '<span class="combleu">La notation de l\'�levage de '.$joueur[0].'</span><br><br>';

if ($joueur[14])
{
	$res=mysql_query('SELECT nom,1,description,mid,categorie,type,couleur,niveau-points FROM modele WHERE uid = '.$_GET['uid'].' AND publique AND pret ORDER BY categorie, niveau-points DESC');
	if (mysql_num_rows($res))
	{
	echo '<span class="combleu">Ce qu\'on peut fabriquer dans l\'atelier de '.$joueur[0].' :</span><br> ';
	$nomobj=$LISTE_MATERIELMAJ;
		echo '<div class="historique">';
	echo '<table align=center cellspacing=0px cellpadding=3px>
		<tr><td align=center><span class=combleu>Objet</span></td><td width=10></td><td align=center><span class=combleu>Nom</span></td><td width=10></td><td align=center><span class=combleu>Niveau</span></td><td width=10></td><td width=400px><span class=combleu>Description</span></td><td width=10></td><td><span class=combleu>Atelier</span></td></tr>';
	
	while ($obj=mysql_fetch_row($res))
	{
		echo '<tr>';
		echo '<td align=center>';
		echo '<img style="background-color:'.$obj[6].'" width=40px src="objets/objet'.$obj[4].$obj[5].'.png"></td><td></td><td align=center><a class="atype5" href="modele.php?mid='.$obj[3].'">'.($obj[4]==2?$nomobj[$obj[5]-1].' ':'').$obj[0].'</a></td><td width=10></td><td align=center>'.$obj[7].'</td><td width=10></td><td style="font-size:smaller;font-style:italic">'.$obj[2].((substr(trim($obj[2]),-1)=='.' or !$obj[2])?'':'.').' Mod�le de '.linkFiche($joueur[0],$_GET['uid']).'</a></td><td width=10></td><td align=center>';
	
		echo '<a class="atype5" href="atelier.php?uid='.$_GET['uid'].'"><img src="icones/atelier.png" width=40 border=0></a>';
		
		echo '</td>';
		echo '</tr>';
	}
	echo '</table>';
	echo '</div><br><br>';
	}
}

$result=mysql_query('SELECT * FROM affixes WHERE uid = '.$_GET['uid']);
if (mysql_num_rows($result))
{
	echo '<span class="combleu">Les '.$AFFIXES.' de '.$joueur[0].' :</span><br> ';
	while($aff=mysql_fetch_row($result))
	{
		echo '<a class="atype5" style="font-style:italic" href="affixe.php?aid='.$aff[0].'">'.$aff[2].'</a><br>';
	}
	echo '<br>';
}

// LES VENTES

include "linkanimalconst.php";
$list_ventes=mysql_query('SELECT '.$LINK_ANIMAL_ATTRIBUTES.', chevaux.repro, chevaux.proprietaire, chevaux.vente, chevaux_comvente.commentaire FROM chevaux LEFT JOIN chevaux_comvente USING(cid) WHERE proprietaire='.$_GET['uid'].' AND ventestatut IN(1,2,3) AND (reserve=0 OR reserve='.$_SESSION['uid'].')'.($_SESSION['assoc']?' AND chevaux.race = '.$_SESSION['raceassoc']:''));

if (mysql_num_rows($list_ventes)):
?>
<script type="text/javascript">
function acheter(cid) {
	if (!confirm('Confirmer l\'achat?'))
		return false;
	else
	{
		GetId('achat'+cid).innerHTML='Achat en cours <img src="temps.gif">';
		
		var xhr = null;
		var argent;
		
		if(window.XMLHttpRequest) // Firefox 
			xhr = new XMLHttpRequest(); 
		else if(window.ActiveXObject) // Internet Explorer 
			xhr = new ActiveXObject("Microsoft.XMLHTTP"); 
		else { // XMLHttpRequest non support� par le navigateur 
			alert("Votre navigateur ne supporte pas les objets XMLHTTPRequest..."); 
			return true; 
		}

		xhr.onreadystatechange=function()
		{
			if (xhr.readyState==4)
			{
				argent=xhr.responseText;
				if (argent.length)
				{
					GetId('achat'+cid).innerHTML='Achat confirm�';
					GetId('argent').innerHTML=argent;
				}
				else
					GetId('achat'+cid).innerHTML='Achat refus�';
				
			}
		}
		// ici
		xhr.open("GET", "acheter.php?rapide=1&cid="+cid, true); 
		xhr.send(null);

	}
	
	return false;
}
</script>
<?php
	echo '<span class="combleu">Les ventes de '.$joueur[0].' :</span> ';
	echo '<div class="" '.((isset($_SESSION['fichestyle']) and $_SESSION['fichestyle'])?'style="width:620;height:350;overflow-y:scroll;overflow-x:hidden"':'').'>';
	
	echo '<table><tr><td align=center><span class=combleu>'.$ANIMALMAJ.'</span></td><td align=center width=240 class=combleu>Commentaire de vente</td><td align=center width=120><span class=combleu>Prix</span></td></tr>';
	while($vente=mysql_fetch_assoc($list_ventes)){
		$reprorest=reproRestantes($vente['race'],$vente['repro']);
		$reprorest=($reprorest<0?10000:$reprorest);
		echo '<tr><td>'.($reprorest<10?' ('.$reprorest.') ':'').linkanimal($vente,1).'<div class="infoanimal historique">'.infoanimal($vente).'</div></td><td>'.($vente['commentaire']?'<div class="comvente">'.nl2br(replaceSmiley($vente['commentaire'],2)).'</div>':'').'</td><td align=center>'.($vente['vente']>0?formatNombre($vente['vente']).'<img src=icones/monnaie.png>':'<a class=atype5 href=enchere.php?cid='.$vente['cid'].'>Enchere</a>');
		if ($_SESSION['argent']>$vente['vente'] and $vente['vente']>0)
			// ici
			echo '<div id="achat'.$vente['cid'].'" style="display:block"><a class=atype5 onclick="return confirm(\'Confirmer cet achat?\');" href="acheter.php?cid='.$vente['cid'].'">Achat &amp; voir</a><br><a class=atype4 onclick="return acheter('.$vente['cid'].');" href="#">Achat &amp; continuer</a></div></td></tr>';
	}
	echo '</table>';
	echo '</div><br>';
endif;

$legendes=mysql_query('SELECT '.$LINK_ANIMAL_ATTRIBUTES.', LEFT(histoire,512) AS histoire,repro FROM chevaux NATURAL JOIN animaux_histoires WHERE proprietaire = '.$_GET['uid'].' AND equipement & 256');
if (mysql_num_rows($legendes)>0):
echo '<span class="combleu">Les l�gendes de '.$joueur[0].' :</span> ';
echo '<div class="" '.((isset($_SESSION['fichestyle']) and $_SESSION['fichestyle'])?'style="width:620;max-height:350;overflow-y:scroll;overflow-x:hidden"':'').'>';

echo '<table><tr><td align=center width=230><span class=combleu>'.$ANIMALMAJ.'</span></td><td class=combleu>L�gende</td></tr>';
while($vente=mysql_fetch_assoc($legendes)){
	$reprorest=reproRestantes($vente['race'],$vente['repro']);
	$reprorest=($reprorest<0?10000:$reprorest);
	echo '<tr><td>'.($reprorest<10?' ('.$reprorest.') ':'').linkanimal($vente,1).'<div class="infoanimal historique">'.infoanimal($vente).'</div></td><td><div class="comvente" style="width:360px;height:100%">'.(replaceSmiley($vente['histoire'],2)).'[...]</div>';
	echo '</td></tr>';
}
echo '</table>';
echo '</div><br>';
endif;

if ($nbanimaux<100)
{
	$query = 'SELECT '.$LINK_ANIMAL_ATTRIBUTES.',repro,lieu,gestante,vente,visite FROM chevaux WHERE proprietaire = '.$_GET['uid'].' ORDER BY race, potentiel DESC';
	$result = mysql_query($query);
	
	if (mysql_num_rows($result))
	{
	echo '<a class=atype5 style="float:right" href=elevage.php?uid='.$_GET['uid'].'>Voir son �levage</a><span class="combleu">Les '.$ANIMAUX.' de '.$joueur[0].' :</span> ';
	echo '<div class="" '.((isset($_SESSION['fichestyle']) and $_SESSION['fichestyle'])?'style="width:620;height:500;overflow-y:scroll;overflow-x:hidden"':'').'>';
	echo '<table cellspacing=0 cellpadding=0>'; 
	getday();
	$yday=$_SESSION['day'];
	 			
	
	
	$i=0;
	$racep=0;
	if (isset($_SESSION['uid']))
		$peutsaillir=mysql_result(mysql_query('SELECT COUNT(1) FROM chevaux WHERE age >= 36 AND sexe = 2 AND proprietaire = '.$_SESSION['uid']),0);
	else $peutsaillir=0;
	
	$tmp_list='';
	$tmp_title='';
	$nb_animaux=0;
	while($joueur = mysql_fetch_array($result)){
		if ($joueur['race']!=$racep)
		{
			if (($i % 3)!=0)
				$tmp_list.='</tr>';
			$i=0;
			
			echo str_replace("%TEST%",$nb_animaux.' '.mot('animal','',0,$nb_animaux>1?1:0),$tmp_title);
			echo $tmp_list;
			
			$nb_animaux=0;
			$tmp_list='';
			$tmp_title='<tr><td colspan=3><span class="combleu">Ses '.mysql_result(mysql_query('SELECT nompluriel FROM race WHERE raceid = '.$joueur['race']),0).' (%TEST%) </span></td></tr>';
		}
		$racep=$joueur['race'];
		if (($i % 3)==0)
			$tmp_list.= '<tr>';
		$tmp_list.= '<td>';
		$nb_animaux++;
		$tmp_list.=caseanimal($joueur,($bloquage==2 && $peutsaillir==0)?1:0);
		$tmp_list.= '</td>';
		if (($i % 3)==2)
			$tmp_list.= '</tr>';
		
		$i++;
		
	}
	echo str_replace("%TEST%",$nb_animaux.' '.mot('animal','',0,$nb_animaux>1?1:0),$tmp_title);
	echo $tmp_list;
	
	echo '</table>';
	echo '</div>';
	}
  
}
else echo '<span class="combleu">Les '.$ANIMAUX.' de '.$joueur[0].' :</span><br>'.$joueur[0].' poss�de trop d\'animaux pour les afficher sur sa fiche. Pour y acc�der, <a class=atype5 href=elevage.php?uid='.$_GET['uid'].'>cliquer ici</a>.';
echo finFenetre();
include "finpage.php";
?>