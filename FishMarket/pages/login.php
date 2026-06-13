<?php
$noSharedBg = true;
$extraHead = '<style>
:root{--brand-cyan:#00e5ff;--input-bg:rgba(0,0,0,0.25);--radius:20px}
body{overflow:hidden;background:radial-gradient(circle at center,#0a1f33 0%,#020710 100%)}
.ai-chatbot-btn,#aiChatbox{display:none!important}
footer{display:none!important}
main{padding:0!important;margin:0!important;max-width:100%!important;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:flex-start}
#bg-canvas{position:fixed;top:0;left:0;width:100%;height:100%;z-index:1;pointer-events:none}
.auth-card-static{position:relative;z-index:2;width:380px;max-height:90vh;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:35px 25px;box-sizing:border-box;backdrop-filter:blur(25px);-webkit-backdrop-filter:blur(25px);box-shadow:0 30px 60px rgba(0,0,0,0.4);display:flex;flex-direction:column;margin:auto;overflow:hidden}
.auth-tabs{position:relative;display:flex;background:rgba(0,0,0,0.25);border-radius:30px;padding:4px;margin-bottom:25px;border:1px solid rgba(255,255,255,0.05);flex-shrink:0}
.auth-tab{flex:1;background:none;border:none;color:var(--muted);font-size:.95rem;font-weight:600;padding:10px 0;cursor:pointer;z-index:2;transition:color .3s ease;text-align:center}
.auth-tab.active{color:#fff}
.tab-active-bg{position:absolute;left:4px;top:4px;width:calc(50% - 4px);height:calc(100% - 8px);background:linear-gradient(135deg,var(--brand-cyan),var(--brand-dark));border-radius:25px;z-index:1;will-change:transform;transform:translate3d(0,0,0);transition:transform .4s cubic-bezier(.25,1,.5,1)}
.auth-slider-view{width:100%;flex:1;min-height:0;overflow:hidden}
.auth-slider-wrapper{display:flex;width:200%;min-height:100%;will-change:transform;transform:translate3d(0,0,0);transition:transform .5s cubic-bezier(.25,1,.5,1)}
.auth-form-container{width:50%;box-sizing:border-box;padding:0 5px;display:flex;flex-direction:column}
.auth-form-body{flex:1}
.auth-form-footer{margin-top:12px;text-align:center;font-size:.85rem;color:var(--muted);flex-shrink:0}
.auth-header{margin-bottom:20px;text-align:center}
.auth-header h2{margin:0;font-size:1.6rem;letter-spacing:1px;background:linear-gradient(135deg,var(--brand-cyan),var(--brand));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.auth-header p{margin:6px 0 0;font-size:.82rem;color:var(--muted)}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:.75rem;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.form-input{width:100%;background:var(--input-bg);border:1px solid var(--border);border-radius:8px;padding:11px 13px;box-sizing:border-box;color:#fff;font-size:.9rem;transition:border-color .3s,box-shadow .3s}
.form-input:focus{outline:none;border-color:var(--brand-cyan);box-shadow:0 0 12px rgba(0,229,255,0.2)}
.submit-button{width:100%;padding:12px;background:linear-gradient(135deg,var(--brand-cyan),var(--brand-dark));border:none;border-radius:8px;color:#fff;font-size:.9rem;font-weight:700;cursor:pointer;box-shadow:0 5px 20px rgba(79,195,247,0.1);transition:transform .2s,box-shadow .2s;margin-top:5px}
.submit-button:hover{transform:translateY(-1px);box-shadow:0 8px 25px rgba(0,229,255,0.35)}
.form-footer{text-align:center;font-size:.85rem;color:var(--muted);margin-top:15px}
.form-footer a{color:var(--brand-cyan);font-weight:600;text-decoration:underline}
</style>';
$extraScripts = '<script>
const canvas=document.getElementById("bg-canvas"),ctx=canvas.getContext("2d"),dpr=window.devicePixelRatio||1;let width=window.innerWidth,height=window.innerHeight;
function resize(){width=window.innerWidth,height=window.innerHeight,canvas.width=width*dpr,canvas.height=height*dpr,ctx.scale(dpr,dpr)}resize(),window.addEventListener("resize",resize);
class Jellyfish{constructor(){this.x=Math.random()*width,this.y=height+Math.random()*200,this.baseRadius=Math.random()*18+12,this.speed=Math.random()*0.25+0.15,this.pulseTimer=Math.random()*100,this.pulseSpeed=Math.random()*0.025+0.015,this.opacity=Math.random()*0.18+0.05;const e=[{r:79,g:195,b:247},{r:0,g:229,b:255},{r:26,g:188,b:156}];this.color=e[Math.floor(Math.random()*e.length)]}update(){this.pulseTimer+=this.pulseSpeed,this.currentRadius=this.baseRadius+Math.sin(this.pulseTimer)*(this.baseRadius*0.22);const e=Math.max(0,Math.cos(this.pulseTimer));this.y-=this.speed+e*0.25,this.x+=Math.sin(this.pulseTimer*0.4)*0.15,this.y<-this.baseRadius*3&&(this.y=height+this.baseRadius*3,this.x=Math.random()*width)}draw(){ctx.save(),ctx.translate(this.x,this.y);const e=this.currentRadius,t=`rgba(${this.color.r},${this.color.g},${this.color.b},${this.opacity})`;ctx.strokeStyle=t,ctx.lineWidth=1.2;for(let s=-2;s<=2;s++){const a=.38*e*s,i=Math.sin(1.5*this.pulseTimer+s)*6;ctx.beginPath(),ctx.moveTo(a,0),ctx.bezierCurveTo(a+.5*i,.6*e,a-i,1.3*e,a+.3*i,2*e),ctx.stroke()}ctx.fillStyle=`rgba(${this.color.r},${this.color.g},${this.color.b},${1.8*this.opacity})`,ctx.beginPath(),ctx.arc(0,-.2*e,.35*e,0,2*Math.PI),ctx.fill(),ctx.fillStyle=t,ctx.beginPath(),ctx.arc(0,0,e,Math.PI,0),ctx.quadraticCurveTo(.5*e,.15*e,0,.08*e),ctx.quadraticCurveTo(-.5*e,.15*e,-e,0),ctx.closePath(),ctx.fill(),ctx.restore()}}
class Plankton{constructor(){this.x=Math.random()*width,this.y=Math.random()*height,this.radius=Math.random()*1.2+.4,this.speedY=Math.random()*0.2+0.1,this.speedX=Math.random()*0.1-0.05,this.opacity=Math.random()*0.3+0.05}update(){this.y-=this.speedY,this.x+=this.speedX+Math.sin(this.y/50)*0.05,this.y<-10&&(this.y=height+10,this.x=Math.random()*width)}draw(){ctx.save(),ctx.fillStyle=`rgba(0,229,255,${this.opacity})`,ctx.beginPath(),ctx.arc(this.x,this.y,this.radius,0,2*Math.PI),ctx.fill(),ctx.restore()}}
const jellyfishList=[];for(let e=0;e<12;e++)jellyfishList.push(new Jellyfish);const planktonList=[];for(let e=0;e<40;e++)planktonList.push(new Plankton);
function animate(){ctx.fillStyle="rgba(2,7,16,0.25)",ctx.fillRect(0,0,width,height),planktonList.forEach(e=>{e.update(),e.draw()}),jellyfishList.forEach(e=>{e.update(),e.draw()}),requestAnimationFrame(animate)}animate();
</script>'; ?>
<canvas id="bg-canvas"></canvas>
<div class="auth-card-static">
    <div class="auth-tabs">
      <div class="tab-active-bg" id="tab-bg"></div>
      <button class="auth-tab active" onclick="switchTo('login')">Sign In</button>
      <button class="auth-tab" onclick="switchTo('register')">Register</button>
    </div>
    <div class="auth-slider-view">
    <div class="auth-slider-wrapper" id="slider-wrapper">
      <div class="auth-form-container">
        <div class="auth-form-body">
          <div class="auth-header">
            <h2>PORT SIGN IN</h2>
            <p>Sign in to your Fish Market account</p>
          </div>
          <form method="post" action="index.php?page=login">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
              <label>Username</label>
              <input type="text" name="username" class="form-input" placeholder="Enter your username" required>
            </div>
            <div class="form-group">
              <label>Password</label>
              <input type="password" name="password" class="form-input" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="submit-button">SIGN IN</button>
          </form>
        </div>
        <div class="auth-form-footer">New partner? <a href="javascript:void(0)" onclick="switchTo('register')">Create an account</a></div>
      </div>
      <div class="auth-form-container">
        <div class="auth-form-body">
          <div class="auth-header">
            <h2>REGISTER</h2>
            <p>Join Fish Market intelligent distribution</p>
          </div>
          <form method="post" action="index.php?page=register">
            <input type="hidden" name="action" value="register">
            <div class="form-group">
              <label>Username</label>
              <input type="text" name="username" class="form-input" placeholder="Choose a username" required>
            </div>
            <div class="form-group">
              <label>Password</label>
              <input type="password" name="password" class="form-input" placeholder="Minimum 8 characters" required>
            </div>
            <div class="form-group">
              <label>Confirm Password</label>
              <input type="password" name="confirm_password" class="form-input" placeholder="Re-enter your password" required>
            </div>
            <div class="form-group">
              <label>Email Address</label>
              <input type="email" name="email" class="form-input" placeholder="your@email.com" required>
            </div>
            <div class="form-group">
              <label>Phone Number</label>
              <input type="tel" name="phone" class="form-input" placeholder="e.g. +60 12-345 6789" required>
            </div>
            <button type="submit" class="submit-button">CREATE ACCOUNT</button>
          </form>
        </div>
        <div class="auth-form-footer">Already have an account? <a href="javascript:void(0)" onclick="switchTo('login')">Sign in</a></div>
      </div>
    </div>
  </div>
</div>
<script>
const sliderWrapper=document.getElementById("slider-wrapper"),tabBg=document.getElementById("tab-bg"),tabs=document.querySelectorAll(".auth-tab");
function switchTo(type){if(type==="login"){sliderWrapper.style.transform="translate3d(0,0,0)";tabBg.style.transform="translate3d(0,0,0)";tabs[0].classList.add("active");tabs[1].classList.remove("active")}else if(type==="register"){sliderWrapper.style.transform="translate3d(-50%,0,0)";tabBg.style.transform="translate3d(100%,0,0)";tabs[0].classList.remove("active");tabs[1].classList.add("active")}}
</script>
