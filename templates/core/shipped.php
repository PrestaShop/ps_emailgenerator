<?php include ('header.php'); ?>

<tr>
	<td align="center" class="titleblock">
		<font size="2" face="Open-sans, sans-serif" color="#555454">
			<span class="title"><?php echo t('Hi {firstname} {lastname},'); ?></span><br/>
			<span class="subtitle"><?php echo t('Thank you for shopping with {shop_name}!'); ?></span>
		</font>
	</td>
</tr>
<tr>
	<td class="space_footer">&nbsp;</td>
</tr>
<tr>
	<td class="box" style="border:1px solid #D6D4D4;">
		<table class="table">
			<tr>
				<td width="10">&nbsp;</td>
				<td>
					<font size="2" face="Open-sans, sans-serif" color="#555454">
						<p data-html-only="1" style="border-bottom:1px solid #D6D4D4;">
							<?php echo t('Order {order_name}'); ?>&nbsp;-&nbsp;<?php echo t('Shipped'); ?>
						</p>
						<span>
							<?php echo t('Your order with the reference <span><strong>{order_name}</strong></span> has been shipped.'); ?><br /> 
							<?php echo t('You will soon receive a URL to track the delivery progress of your package.'); ?>
						</span>
					</font>
				</td>
				<td width="10">&nbsp;</td>
			</tr>
		</table>
	</td>
</tr>
<tr>
	<td class="linkbelow">
		<span>
			<?php echo t('You can now place orders on our shop:'); ?> <a href="{shop_url}">{shop_name}</a>
		</span>
	</td>
</tr>

<?php include ('footer.php'); ?>
