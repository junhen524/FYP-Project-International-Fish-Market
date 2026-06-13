<?php
// Shared underwater fish animation background for all FishMarket pages
ob_start();
?>
<style>
#bg-fish-canvas {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    z-index: 0;
    pointer-events: none;
}
.page-wrapper {
    position: relative;
    z-index: 1;
}
</style>
<canvas id="bg-fish-canvas"></canvas>
<script>
(function(){
const canvas = document.getElementById('bg-fish-canvas');
if (!canvas) return;
const ctx = canvas.getContext('2d');
const dpr = window.devicePixelRatio || 1;
let width = window.innerWidth, height = window.innerHeight;
function resize() {
    width = window.innerWidth;
    height = window.innerHeight;
    canvas.width = width * dpr;
    canvas.height = height * dpr;
    ctx.scale(dpr, dpr);
}
resize();
window.addEventListener('resize', resize);

class Fish {
    constructor() {
        this.x = Math.random() * width;
        this.y = Math.random() * height;
        this.size = Math.random() * 12 + 8;
        this.speed = Math.random() * 0.25 + 0.08;
        this.angle = Math.random() * Math.PI * 2;
        this.wiggleSpeed = Math.random() * 0.035 + 0.015;
        this.wiggleRange = Math.random() * 0.1 + 0.05;
        this.wiggleCount = Math.random() * 100;
        const colors = ['rgba(79, 195, 247, 0.45)','rgba(0, 229, 255, 0.45)','rgba(26, 188, 156, 0.35)','rgba(129, 212, 250, 0.4)'];
        this.color = colors[Math.floor(Math.random() * colors.length)];
    }
    update() {
        this.wiggleCount += this.wiggleSpeed;
        const a = this.angle + Math.sin(this.wiggleCount) * this.wiggleRange;
        this.x += Math.cos(a) * this.speed;
        this.y += Math.sin(a) * this.speed;
        const p = this.size * 3;
        if (this.x < -p) this.x = width + p;
        if (this.x > width + p) this.x = -p;
        if (this.y < -p) this.y = height + p;
        if (this.y > height + p) this.y = -p;
    }
    draw() {
        ctx.save();
        ctx.translate(this.x, this.y);
        const a = this.angle + Math.sin(this.wiggleCount) * this.wiggleRange;
        ctx.rotate(a);
        ctx.fillStyle = this.color;
        ctx.beginPath();
        ctx.moveTo(this.size, 0);
        ctx.quadraticCurveTo(0, -this.size * 0.35, -this.size, 0);
        ctx.quadraticCurveTo(0, this.size * 0.35, this.size, 0);
        ctx.closePath();
        ctx.fill();
        ctx.beginPath();
        ctx.moveTo(-this.size + 2, 0);
        const t = Math.sin(this.wiggleCount) * 4;
        ctx.lineTo(-this.size - this.size * 0.4, -this.size * 0.3 + t);
        ctx.lineTo(-this.size - this.size * 0.2, 0);
        ctx.lineTo(-this.size - this.size * 0.4, this.size * 0.3 + t);
        ctx.closePath();
        ctx.fill();
        ctx.restore();
    }
}
class Bubble {
    constructor() {
        this.x = Math.random() * width;
        this.y = height + Math.random() * 100;
        this.radius = Math.random() * 2.5 + 0.5;
        this.speed = Math.random() * 0.35 + 0.1;
        this.opacity = Math.random() * 0.4 + 0.1;
    }
    update() {
        this.y -= this.speed;
        this.x += Math.sin(this.y / 30) * 0.4;
        if (this.y < -10) { this.y = height + 10; this.x = Math.random() * width; }
    }
    draw() {
        ctx.save();
        ctx.strokeStyle = 'rgba(255,255,255,' + this.opacity + ')';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
        ctx.stroke();
        ctx.restore();
    }
}
const fishes = [];
for (let i = 0; i < 15; i++) fishes.push(new Fish());
const bubbles = [];
for (let i = 0; i < 40; i++) bubbles.push(new Bubble());
function animate() {
    ctx.fillStyle = 'rgba(2, 8, 19, 0.2)';
    ctx.fillRect(0, 0, width, height);
    bubbles.forEach(b => { b.update(); b.draw(); });
    fishes.forEach(f => { f.update(); f.draw(); });
    requestAnimationFrame(animate);
}
animate();
})();
</script>
<?php
$bgSharedHTML = ob_get_clean();
$bgSharedHead = '<style>#bg-fish-canvas{position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none}.page-wrapper{position:relative;z-index:1}</style>';
