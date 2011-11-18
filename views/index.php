<div class="metro-pivot">
<div class='pivot-item'>
	<h3 name="list">channel</h3>
	<ul class="channel_list">
	<?php foreach( $channels as $ch ){ ?>
	<li id="ch_<?php print $ch['id']; ?>" class="<?php if($ch['cnt']>0){ print "new"; } ?>"><span class="ch_name"><?php print $ch['name']; ?></span>(<span class="ch_num"><?php print $ch['cnt']; ?></span>)</li>
	<?php } ?>
	</ul>
	<form method="POST" id="search_form">
	<input type="text" name="word"  id="keyword" />
	<select name="channel" id="channel_select">
		<option value="" >----</option>
		<?php foreach( $channels as $ch ){ ?>
			<option value="<?php print $ch['id']; ?>"><?php print $ch['name']; ?></option>
		<?php } ?>
	</select>
	<input type="submit" name="search" value="search"/>
	</form>
	<input type="button" id="unread_reset" value="unread reset" />
</div>
<div class='pivot-item'>
	<h3 id="ch_name" name="channel" ></h3>
	<form method="POST" id="post_form">
		<input type="text" name="post" id="message" /><input type="submit" value="post" />
	</form>
	<hr/>
	<table id="list" class="list">
		<thead>
			<tr>
				<th>nick</th><th>log</th><th>time</th>
			</tr>
		</thead>
		<tbody></tbody>
	</table>
	<div id="ch_foot"></div>
</div>
<div class='pivot-item'>
	<h3 name="search"></h3>
	<span id="search_result_message">search result</span>
	<table id="search-list" class="list">
		<thead>
		<tr>
			<th>channel</th><th>nick</th><th>log</th><th>time</th>
		</tr>
		</thead>
		<tbody></tbody>
	</table>
	<div id="search_foot"></div>
</div>
<script>
$(function(){
    var Class = function(){ return function(){this.initialize.apply(this,arguments)}};

	var TiarraMetroClass = new Class();
	TiarraMetroClass.prototype = {
		initialize: function( param ){
			var self = this;
			this.max_id = param.max_id;
			this.currentChannel = param.currentChannel;
			this.chLogs = param.chLogs;
			this.pickup_word = param.pickup_word;
			this.updating = param.updating;
			this.jsConf = param.jsConf;
			this.mountPoint = param.mountPoint;

			this.autoReload =  setInterval(function(){self.reload();}, this.jsConf["update_time"]*1000);
			this.htmlInitialize();
		},
		htmlInitialize: function(){
			var self = this;
			$('ul.channel_list li').click(function(){
				channel_id = this.id.substring(3);
				channel_name = self.getChannelName(channel_id);

				self.selectChannel( channel_id, channel_name );

				self.myPushState(channel_name,'/channel/'+channel_id);
			});

			$('form#post_form').submit(function(){
				message = $('input#message').val();
				if( message.length == 0 ){ return false; }
				$.ajax({
					url:self.mountPoint+'/api/post/',
					data:{
						channel_id:self.currentChannel,
						post:message,
					},
					dataType:'json',
					type:'POST',
				});
				console.log(self.mountPoint+'/api/post/');
				$('input#message').val('');
				return false;
			});

			$('form#search_form').submit(function(){
				kw = $('input#keyword').val();
				if( kw.length == 0 ){ return false; }

				$('#search-list tbody tr').each(function( i,e ){ $(e).remove(); });
				$('div#search_foot').html( 'now searching...' );

				$('div.headers span.header[name=search]').html( 'search' );
				if( ! $("div.metro-pivot").data("controller").isCurrentByName( 'search' ) ){
					$("div.metro-pivot").data("controller").goToItemByName('search');
				}

				d = { keyword:kw };
				select = $('select#channel_select option:selected').val();
				if( select.length ){
					d['channel_id'] = select;
				}

				$.ajax({
					url:self.mountPoint+'/api/search/',
					data:d,
					dataType:'json',
					type:'POST',
					success:function(json){
						$('#search_result_message').text('search result '+json.length);
						if( json.length	){
							$.each( json, self.add_result ); 
						}
						self.addCloseButton();
					}
				})
				return false;
			});

			$('input#unread_reset').click(function(){
				$.ajax({
					url:self.mountPoint+'/api/reset/unread',
					dataType:'json',
					type:'POST',
				});
				$('.channel_list li').attr('class','');
				$('.channel_list li span.ch_num').text(0);
			});

			$(window).bind('popstate', function(event) {
				switch( event.originalEvent.state ){
					case '/':
						$("div.metro-pivot").data("controller").goToItemByName( 'list' );
						break;
					case '/search/':
						$("div.metro-pivot").data("controller").goToItemByName( 'search');
						break;
					case null:
						break;
					default:
						channel_id = event.originalEvent.state.substring( event.originalEvent.state.lastIndexOf( '/' )+1 );
						channel_name = self.getChannelName(channel_id);
						self.selectChannel(channel_id,channel_name);
						break;
				}
			}, false);

			$("div.metro-pivot").metroPivot({
				clickedItemHeader:function(i){
					switch( i ){
						case '0': //channel list
							self.myPushState( 'channel list','/' );
							break;
						case '1':
							self.myPushState($('div.headers span.header[index=1]').text(),'/channel/'+self.currentChannel );
							break;
						case '2': //search
							self.myPushState('search','/search/' );
							break;
					}
				},
				controlInitialized:function(){
					default_pivot = '<?php print $pivot; ?>';
					switch( default_pivot ){
						case 'channel':
							self.loadChannel( <?php print $default_channel['id']; ?>,'<?php print $default_channel['name'];  ?>');
						default:
							//$("div.metro-pivot").data("controller").goToItemByName(default_pivot);
							$("div.metro-pivot").data("controller").goToItemByName( default_pivot);
							break;
						case 'list':
						case 'default':
							break;
					}
				}
			});
		},
		reload: function(){
			var self = this;
			if( self.updating ){ return; }
			self.updating = true;
			$.ajax({
				url:self.mountPoint+'/api/logs/',
				dataType:'json',
				type:'POST',
				data:{max_id:self.max_id,current:self.currentChannel},
				success:function(json){
					if( json['update'] ){
						$.each( json['logs'], function(channel_id, logs){
							$.each( self.pickup_word,function(j,w){
								logs = $.map( logs, function( log,i){
									//if( log.id <= max_id ){ return null; }
									if( $("#"+log.id ).length ){ return null; }
									if( log.log.indexOf(w) >= 0 ){
										$.jGrowl( log.nick+':'+ log.log +'('+self.getChannelName(channel_id)+')' ,{ header: 'keyword hit',life: 5000 } );
										log.log = log.log.replace( w, '<span class="pickup">'+w+'</span>' );
										$('#ch_'+channel_id).attr('class','hit');
									}
									return log;
								});
							});
							if( ! logs.length ){ return; }
							
							self.chLogs[channel_id] = logs.concat(self.chLogs[channel_id]).slice(0,30);

							if( channel_id == self.currentChannel ){
								$.each( logs.reverse(), self.add_log );
							}else{
								if( $('#ch_'+channel_id).attr('class') != 'hit' ){
									$('#ch_'+channel_id).attr('class','new');
								}
								num = $('#ch_'+channel_id+' span.ch_num');
								num.text( num.text()-0+logs.length );
							}
						});
						self.max_id = json['max_id'];
					}
					self.updating = false;
				},
				error:function(){
					self.updating = false;
				}
			});	 
		},
		logFilter : function(log){
			return log;
		},
		add_log:function( i, log ){
			$('#list tbody').prepend('<tr id="'+log.id+'"><td class="name '+log.nick+'">'+log.nick+'</td><td class="log '+((log.is_notice == 1)?'notice':'')+'">'+log.log+'</td><td class="time">'+log.time.substring(5)+'</td></tr>');
		},
		more_log : function( i,log ){
			$('#list tbody').append('<tr id="'+log.id+'"><td class="name '+log.nick+'">'+log.nick+'</td><td class="log '+((log.is_notice == 1)?'notice':'')+'">'+log.log+'</td><td class="time">'+log.time.substring(5)+'</td></tr>');
		},
		add_result : function( i, log ){
			$('#search-list tbody').prepend('<tr><td class="channel">'+log.channel_name+'</td><td class="name '+log.nick+'">'+log.nick+'</td><td class="log '+(log.is_notice==1?'notice':'')+'">'+log.log+'</td><td class="time">'+log.time.substring(5)+'</td></tr>');
		},
		getChannelName : function( i ){
			return $('li#ch_'+i+' span.ch_name').text();
		},
		myPushState : function( name, url ){
			if( history.pushState ){
				history.pushState( window.location.pathname ,name, url );
			}
		},
		selectChannel : function( channel_id, channel_name ){
			this.currentChannel = channel_id;

			$('#list tbody tr').each(function( i,e ){ $(e).remove(); });
			$('div#ch_foot').html('');

			this.loadChannel( channel_id, channel_name);
		
			$("div.metro-pivot").data("controller").goToItemByName('channel');
			//scrollTo(0,0);
		},
		loadChannel : function( channel_id, channel_name ){
			$('div.headers span.header[name=channel]').html( channel_name );
			$('#ch_'+channel_id).attr('class','');
			$('#ch_'+channel_id+' span.ch_num').text(0);
			
			$.each( [].concat( this.chLogs[channel_id]).reverse() , this.add_log );

			$.ajax({
				url:this.mountPoint+'/api/read/'+channel_id,
				dataType:'json',
				type:'POST',
			});

			if( this.chLogs[channel_id].length >= 30 ){
				this.addMoreButton( );
			}
		},
		addMoreButton : function(){
			button = $('<input type="button" value="more" />');
			button.click(function(){
				$('div#ch_foot').html( 'more loading...' );
				$.ajax({
					url:this.mountPoint+'/api/logs/'+this.currentChannel,
					data:{
						//start: $('#list tbody tr').length ,
						prev_id: $('#list tbody tr').last().attr('id'),
					},
					dataType:'json',
					type:'POST',
					success:function(json){
						if( json['error'] ){ return; }
						$.each(json['logs'],more_log);
						this.addMoreButton( );
					}
				});
			});
			$('div#ch_foot').html(button);
		},
		addCloseButton : function(){
			button = $('<input type="button" value="close" />');
			button.click(function(){
				$('div.headers span.header[name=search]').html( '' );
				if( ! $("div.metro-pivot").data("controller").isCurrentByName( 'list' ) ){
					$("div.metro-pivot").data("controller").goToItemByName('list');
				}
			});
			$('div#search_foot').html(button);
		}
	};

	tiarraMetro = new TiarraMetroClass({
		max_id : '<?php print $max_id; ?>',
		currentChannel : <?php print $default_channel['id']<0?"null":$default_channel['id']; ?>,
		chLogs : <?php print json_encode($logs); ?>,
		pickup_word : <?php print json_encode($pickup); ?>,
		updating : false,
		jsConf : <?php print json_encode($jsConf); ?>,
		mountPoint : "<?php print $mount_point; ?>",
	});
});
</script>
</div>
