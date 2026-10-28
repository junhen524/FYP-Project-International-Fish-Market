<?php
// Seabed ecosystem background for hero section
// Extracted from seabackground.html
$bgSeabedHead = <<<'CSS'
<style>
#seabed-canvas {
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    z-index: 1;
    display: block;
}
</style>
CSS;

$bgSeabedScripts = <<<'JS'
<script>
(function(){
const canvas = document.getElementById('seabed-canvas');
if (!canvas) return;
const ctx = canvas.getContext('2d');
const dpr = window.devicePixelRatio || 1;
let width = window.innerWidth, height = window.innerHeight;
function getSeabedY(x) {
    const wave = Math.sin((x / width) * Math.PI) * 18;
    return height - 12 - wave;
}
function resize() {
    width = window.innerWidth;
    height = window.innerHeight;
    canvas.width = width * dpr;
    canvas.height = height * dpr;
    ctx.scale(dpr, dpr);
}
resize();
window.addEventListener('resize', resize);
class FreeFish {
    constructor() {
        this.x = Math.random() * width;
        this.y = Math.random() * (height - 80);
        this.z = Math.random() * 2 - 1;
        this.angle = Math.random() * Math.PI * 2;
        this.speed = Math.random() * 0.4 + 0.25;
        this.turnTimer = Math.random() * 100;
        this.turnSpeed = Math.random() * 0.02 + 0.005;
        this.zSpeed = (Math.random() * 0.003 + 0.001) * (Math.random() > 0.5 ? 1 : -1);
        this.size = Math.random() * 10 + 10;
        this.wiggleCount = Math.random() * 100;
        this.wiggleSpeed = Math.random() * 0.12 + 0.06;
        const colors = ['rgba(0, 229, 255, ','rgba(79, 195, 247, ','rgba(26, 188, 156, '];
        this.colorBase = colors[Math.floor(Math.random() * colors.length)];
    }
    update() {
        this.turnTimer += this.turnSpeed;
        this.angle += Math.sin(this.turnTimer) * 0.012;
        this.angle += (Math.random() * 0.01 - 0.005);
        this.z += this.zSpeed;
        if (this.z > 1 || this.z < -1) this.zSpeed = -this.zSpeed;
        const scale = (this.z + 1.8) / 2.8;
        const actualSpeed = this.speed * scale;
        this.x += Math.cos(this.angle) * actualSpeed;
        this.y += Math.sin(this.angle) * actualSpeed;
        const limitY = getSeabedY(this.x) - 40;
        if (this.y > limitY) { this.y = limitY; this.angle = -this.angle; }
        if (this.y < 30) { this.y = 30; this.angle = -this.angle; }
        this.wiggleCount += this.wiggleSpeed;
        const padding = this.size * 3;
        if (this.x < -padding) this.x = width + padding;
        if (this.x > width + padding) this.x = -padding;
    }
    draw() {
        const scale = (this.z + 1.8) / 2.8;
        const s = this.size * scale;
        const opacity = ((this.z + 1.2) / 2.2) * 0.22;
        ctx.save();
        ctx.translate(this.x, this.y);
        ctx.rotate(this.angle);
        ctx.fillStyle = this.colorBase + opacity + ')';
        ctx.beginPath();
        ctx.moveTo(s, 0);
        ctx.quadraticCurveTo(0, -s * 0.38, -s, 0);
        ctx.quadraticCurveTo(0, s * 0.38, s, 0);
        ctx.closePath();
        ctx.fill();
        ctx.beginPath();
        ctx.moveTo(-s + 1, 0);
        const t = Math.sin(this.wiggleCount) * (s * 0.25);
        ctx.lineTo(-s - s * 0.35, -s * 0.3 + t);
        ctx.lineTo(-s - s * 0.2, 0);
        ctx.lineTo(-s - s * 0.35, s * 0.3 + t);
        ctx.closePath();
        ctx.fill();
        ctx.restore();
    }
}
class Kelp {
    constructor(x) {
        this.x = x;
        this.height = Math.random() * 120 + 80;
        this.swaySpeed = Math.random() * 0.015 + 0.01;
        this.swayRange = Math.random() * 15 + 10;
        this.swayOffset = Math.random() * 100;
        this.width = Math.random() * 6 + 4;
        const g = ['rgba(26, 188, 156, 0.15)','rgba(18, 120, 98, 0.12)','rgba(38, 166, 154, 0.15)'];
        this.color = g[Math.floor(Math.random() * g.length)];
    }
    draw(time) {
        const sway = Math.sin(time * this.swaySpeed + this.swayOffset) * this.swayRange;
        const seabedY = getSeabedY(this.x);
        ctx.save();
        ctx.strokeStyle = this.color;
        ctx.lineWidth = this.width;
        ctx.lineCap = 'round';
        ctx.beginPath();
        ctx.moveTo(this.x, seabedY);
        ctx.bezierCurveTo(this.x + sway * 0.4, seabedY - this.height * 0.4, this.x - sway * 0.8, seabedY - this.height * 0.7, this.x + sway, seabedY - this.height);
        ctx.stroke();
        ctx.restore();
    }
}
class Crab {
    constructor() {
        this.x = Math.random() * (width - 100) + 50;
        this.speed = (Math.random() * 0.2 + 0.12) * (Math.random() > 0.5 ? 1 : -1);
        this.walkTimer = Math.random() * 100;
        this.size = Math.random() * 4 + 9;
    }
    update() {
        this.x += this.speed;
        this.walkTimer += Math.abs(this.speed) * 0.4;
        if (this.x < 30 || this.x > width - 30) this.speed = -this.speed;
    }
    draw() {
        const crabY = getSeabedY(this.x);
        ctx.save();
        ctx.translate(this.x, crabY - 1);
        ctx.strokeStyle = 'rgba(239, 83, 80, 0.22)';
        ctx.fillStyle = 'rgba(239, 83, 80, 0.28)';
        ctx.lineWidth = 1.3;
        const leg = Math.sin(this.walkTimer * 5) * 2.5;
        for (let i = -1; i <= 1; i++) {
            ctx.beginPath(); ctx.moveTo(-this.size, i * 2); ctx.lineTo(-this.size - 4, i * 2 + leg); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(this.size, i * 2); ctx.lineTo(this.size + 4, i * 2 - leg); ctx.stroke();
        }
        ctx.beginPath(); ctx.arc(-this.size * 0.7, -this.size * 0.6, 2.5, 0, Math.PI * 2); ctx.fill();
        ctx.beginPath(); ctx.arc(this.size * 0.7, -this.size * 0.6, 2.5, 0, Math.PI * 2); ctx.fill();
        ctx.beginPath(); ctx.ellipse(0, 0, this.size, this.size * 0.7, 0, 0, Math.PI * 2); ctx.fill();
        ctx.restore();
    }
}
class Shell {
    constructor(x) { this.x = x; this.size = Math.random() * 5 + 11; this.angle = Math.random() * 0.6 - 0.3; }
    draw() {
        const shellY = getSeabedY(this.x);
        ctx.save(); ctx.translate(this.x, shellY - 0.5); ctx.rotate(this.angle);
        ctx.fillStyle = 'rgba(255, 235, 204, 0.12)'; ctx.strokeStyle = 'rgba(255, 235, 204, 0.18)'; ctx.lineWidth = 0.8;
        ctx.beginPath(); ctx.moveTo(0, 0); ctx.arc(0, 0, this.size, Math.PI, 0, false); ctx.closePath(); ctx.fill(); ctx.stroke();
        ctx.beginPath(); for (let a = Math.PI + 0.4; a < Math.PI * 2 - 0.2; a += 0.5) { ctx.moveTo(0, 0); ctx.lineTo(Math.cos(a) * this.size, Math.sin(a) * this.size); } ctx.stroke();
        ctx.restore();
    }
}
class Conch {
    constructor(x) { this.x = x; this.size = Math.random() * 5 + 11; this.angle = Math.random() * 0.8 - 0.4; }
    draw() {
        const conchY = getSeabedY(this.x);
        ctx.save(); ctx.translate(this.x, conchY - 1); ctx.rotate(this.angle);
        ctx.fillStyle = 'rgba(255, 204, 188, 0.14)'; ctx.strokeStyle = 'rgba(255, 204, 188, 0.2)'; ctx.lineWidth = 0.8;
        ctx.beginPath(); ctx.ellipse(0, 0, this.size, this.size * 0.7, 0, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
        ctx.beginPath(); ctx.ellipse(-this.size * 0.4, -this.size * 0.2, this.size * 0.7, this.size * 0.5, 0.2, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
        ctx.beginPath(); ctx.ellipse(-this.size * 0.7, -this.size * 0.3, this.size * 0.5, this.size * 0.35, 0.4, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(-this.size, -this.size * 0.4); ctx.lineTo(-this.size * 1.3, -this.size * 0.5); ctx.lineTo(-this.size * 0.8, -this.size * 0.1); ctx.closePath(); ctx.fill(); ctx.stroke();
        ctx.restore();
    }
}
class MarineSnow {
    constructor() {
        this.x = Math.random() * width; this.y = Math.random() * height;
        this.radius = Math.random() * 1.2 + 0.4; this.speedY = Math.random() * 0.15 + 0.05;
        this.opacity = Math.random() * 0.2 + 0.05;
    }
    update() {
        this.y -= this.speedY; this.x += Math.sin(this.y / 60) * 0.05;
        if (this.y < -10) { this.y = height + 10; this.x = Math.random() * width; }
    }
    draw() { ctx.fillStyle = 'rgba(0, 229, 255, ' + this.opacity + ')'; ctx.beginPath(); ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2); ctx.fill(); }
}
const fishes = []; for (let i = 0; i < 60; i++) fishes.push(new FreeFish());
const kelps = []; for (let i = 0; i < 12; i++) kelps.push(new Kelp(Math.random() * (width - 100) + 50));
const crabs = [new Crab(), new Crab(), new Crab()];
const shells = []; for (let i = 0; i < 3; i++) shells.push(new Shell(Math.random() * (width - 200) + 100));
const conches = []; for (let i = 0; i < 3; i++) conches.push(new Conch(Math.random() * (width - 200) + 100));
const snowList = []; for (let i = 0; i < 30; i++) snowList.push(new MarineSnow());
let animationTime = 0;
function animate() {
    animationTime += 1;
    ctx.fillStyle = '#01040a'; ctx.fillRect(0, 0, width, height);
    snowList.forEach(s => { s.update(); s.draw(); });
    ctx.fillStyle = '#040b17'; ctx.beginPath(); ctx.moveTo(0, height);
    for (let x = 0; x <= width; x += 10) ctx.lineTo(x, getSeabedY(x));
    ctx.lineTo(width, height); ctx.closePath(); ctx.fill();
    ctx.strokeStyle = 'rgba(0, 229, 255, 0.05)'; ctx.lineWidth = 1.0; ctx.beginPath(); ctx.moveTo(0, getSeabedY(0));
    for (let x = 0; x <= width; x += 10) ctx.lineTo(x, getSeabedY(x)); ctx.stroke();
    shells.forEach(s => s.draw()); conches.forEach(c => c.draw());
    crabs.forEach(c => { c.update(); c.draw(); });
    kelps.forEach(k => k.draw(animationTime));
    fishes.sort((a, b) => a.z - b.z); fishes.forEach(f => { f.update(); f.draw(); });
    requestAnimationFrame(animate);
}
animate();
})();
</script>
JS;
