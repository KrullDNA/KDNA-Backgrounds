/**
 * KDNA Gradient Engine v2.1.15
 * WebGL mesh gradient with optional glass refraction second pass,
 * and a Canvas 2D fallback.
 */

(function (window) {
    'use strict';

    function mix01(a, b, t) { return a + (b - a) * t; }

    function hexToNorm(hex) {
        hex = hex.replace('#', '');
        if (hex.length === 3) hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
        var n = parseInt(hex, 16);
        return [((n >> 16) & 255) / 255, ((n >> 8) & 255) / 255, (n & 255) / 255];
    }

    function supportsWebGL() {
        try {
            var c = document.createElement('canvas');
            var ctx = c.getContext('webgl') || c.getContext('experimental-webgl');
            return !!ctx;
        } catch (e) { return false; }
    }

    /* ═══════════════════════════════════════
     * MiniGl
     * ═══════════════════════════════════════ */
    function MiniGl(canvas, width, height) {
        var self = this;
        self.canvas = canvas;
        self.gl = canvas.getContext('webgl', { antialias: true }) ||
                  canvas.getContext('experimental-webgl', { antialias: true });
        self.meshes = [];
        var gl = self.gl;

        /* Uniform */
        self.Uniform = function (props) {
            this.type = 'float';
            Object.assign(this, props);
            var m = { float: '1f', int: '1i', vec2: '2fv', vec3: '3fv', vec4: '4fv', mat4: 'Matrix4fv' };
            this.typeFn = m[this.type] || '1f';
        };
        self.Uniform.prototype.update = function (loc) {
            if (this.value === undefined) return;
            if (this.typeFn.indexOf('Matrix') === 0) {
                gl['uniform' + this.typeFn](loc, this.transpose || false, this.value);
            } else {
                gl['uniform' + this.typeFn](loc, this.value);
            }
        };
        self.Uniform.prototype.getDeclaration = function (name, type, length) {
            if (this.excludeFrom === type) return '';
            if (this.type === 'array') {
                return this.value[0].getDeclaration(name, type, this.value.length) +
                    '\nconst int ' + name + '_length = ' + this.value.length + ';';
            }
            if (this.type === 'struct') {
                var sn = name.replace('u_', '');
                sn = sn.charAt(0).toUpperCase() + sn.slice(1);
                var body = '', e = Object.entries(this.value);
                for (var i = 0; i < e.length; i++) body += e[i][1].getDeclaration(e[i][0], type).replace(/^uniform/, '') + '\n';
                return 'uniform struct ' + sn + ' {\n' + body + '} ' + name + (length > 0 ? '[' + length + ']' : '') + ';';
            }
            return 'uniform ' + this.type + ' ' + name + (length > 0 ? '[' + length + ']' : '') + ';';
        };

        /* Material */
        self.Material = function (vertSrc, fragSrc, uniforms) {
            var mat = this;
            mat.uniforms = uniforms || {};
            mat.uniformInstances = [];
            function compile(type, src) {
                var s = gl.createShader(type);
                gl.shaderSource(s, src); gl.compileShader(s);
                if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) console.error('KDNA Shader:', gl.getShaderInfoLog(s));
                return s;
            }
            function decls(u, t) {
                var out = '', e = Object.entries(u);
                for (var i = 0; i < e.length; i++) out += e[i][1].getDeclaration(e[i][0], t) + '\n';
                return out;
            }
            var pfx = '\nprecision highp float;\n';
            mat.vertexSource = pfx + 'attribute vec4 position;\nattribute vec2 uv;\nattribute vec2 uvNorm;\n' +
                decls(self.commonUniforms, 'vertex') + decls(uniforms, 'vertex') + vertSrc;
            mat.fragmentSource = pfx + decls(self.commonUniforms, 'fragment') + decls(uniforms, 'fragment') + fragSrc;
            mat.vertexShader = compile(gl.VERTEX_SHADER, mat.vertexSource);
            mat.fragmentShader = compile(gl.FRAGMENT_SHADER, mat.fragmentSource);
            mat.program = gl.createProgram();
            gl.attachShader(mat.program, mat.vertexShader);
            gl.attachShader(mat.program, mat.fragmentShader);
            gl.linkProgram(mat.program);
            if (!gl.getProgramParameter(mat.program, gl.LINK_STATUS)) console.error('KDNA Program:', gl.getProgramInfoLog(mat.program));
            gl.useProgram(mat.program);
            mat.attachUniforms(undefined, self.commonUniforms);
            mat.attachUniforms(undefined, mat.uniforms);
        };
        self.Material.prototype.attachUniforms = function (name, uniforms) {
            var mat = this;
            if (name === undefined) {
                var e = Object.entries(uniforms);
                for (var i = 0; i < e.length; i++) mat.attachUniforms(e[i][0], e[i][1]);
            } else if (uniforms.type === 'array') {
                for (var j = 0; j < uniforms.value.length; j++) mat.attachUniforms(name + '[' + j + ']', uniforms.value[j]);
            } else if (uniforms.type === 'struct') {
                var s = Object.entries(uniforms.value);
                for (var k = 0; k < s.length; k++) mat.attachUniforms(name + '.' + s[k][0], s[k][1]);
            } else {
                mat.uniformInstances.push({ uniform: uniforms, location: gl.getUniformLocation(mat.program, name) });
            }
        };

        /* Attribute */
        self.Attribute = function (props) {
            this.type = gl.FLOAT; this.normalized = false;
            this.buffer = gl.createBuffer();
            Object.assign(this, props); this.update();
        };
        self.Attribute.prototype.update = function () {
            if (this.values !== undefined) {
                gl.bindBuffer(this.target, this.buffer);
                gl.bufferData(this.target, this.values, gl.STATIC_DRAW);
            }
        };
        self.Attribute.prototype.attach = function (name, program) {
            var loc = gl.getAttribLocation(program, name);
            if (this.target === gl.ARRAY_BUFFER) {
                gl.enableVertexAttribArray(loc);
                gl.vertexAttribPointer(loc, this.size, this.type, this.normalized, 0, 0);
            }
            return loc;
        };
        self.Attribute.prototype.use = function (loc) {
            gl.bindBuffer(this.target, this.buffer);
            if (this.target === gl.ARRAY_BUFFER) {
                gl.enableVertexAttribArray(loc);
                gl.vertexAttribPointer(loc, this.size, this.type, this.normalized, 0, 0);
            }
        };

        /* PlaneGeometry */
        self.PlaneGeometry = function (w, h, xSeg, ySeg, orient) {
            gl.createBuffer();
            this.attributes = {
                position: new self.Attribute({ target: gl.ARRAY_BUFFER, size: 3 }),
                uv: new self.Attribute({ target: gl.ARRAY_BUFFER, size: 2 }),
                uvNorm: new self.Attribute({ target: gl.ARRAY_BUFFER, size: 2 }),
                index: new self.Attribute({ target: gl.ELEMENT_ARRAY_BUFFER, size: 3, type: gl.UNSIGNED_SHORT })
            };
            this.setTopology(xSeg, ySeg);
            this.setSize(w, h, orient);
        };
        self.PlaneGeometry.prototype.setTopology = function (xSeg, ySeg) {
            xSeg = xSeg || 1; ySeg = ySeg || 1;
            this.xSegCount = xSeg; this.ySegCount = ySeg;
            this.vertexCount = (xSeg + 1) * (ySeg + 1);
            this.quadCount = xSeg * ySeg * 2;
            this.attributes.uv.values = new Float32Array(2 * this.vertexCount);
            this.attributes.uvNorm.values = new Float32Array(2 * this.vertexCount);
            this.attributes.index.values = new Uint16Array(3 * this.quadCount);
            for (var y = 0; y <= ySeg; y++) {
                for (var x = 0; x <= xSeg; x++) {
                    var idx = y * (xSeg + 1) + x;
                    this.attributes.uv.values[2 * idx] = x / xSeg;
                    this.attributes.uv.values[2 * idx + 1] = 1 - y / ySeg;
                    this.attributes.uvNorm.values[2 * idx] = (x / xSeg) * 2 - 1;
                    this.attributes.uvNorm.values[2 * idx + 1] = 1 - (y / ySeg) * 2;
                    if (x < xSeg && y < ySeg) {
                        var qi = y * xSeg + x;
                        this.attributes.index.values[6 * qi] = idx;
                        this.attributes.index.values[6 * qi + 1] = idx + 1 + xSeg;
                        this.attributes.index.values[6 * qi + 2] = idx + 1;
                        this.attributes.index.values[6 * qi + 3] = idx + 1;
                        this.attributes.index.values[6 * qi + 4] = idx + 1 + xSeg;
                        this.attributes.index.values[6 * qi + 5] = idx + 2 + xSeg;
                    }
                }
            }
            this.attributes.uv.update(); this.attributes.uvNorm.update(); this.attributes.index.update();
        };
        self.PlaneGeometry.prototype.setSize = function (w, h, orient) {
            w = w || 1; h = h || 1; orient = orient || 'xz';
            this.width = w; this.height = h;
            if (!this.attributes.position.values || this.attributes.position.values.length !== 3 * this.vertexCount)
                this.attributes.position.values = new Float32Array(3 * this.vertexCount);
            var hw = w / -2, hh = h / -2, sw = w / this.xSegCount, sh = h / this.ySegCount;
            for (var yy = 0; yy <= this.ySegCount; yy++) {
                var py = hh + yy * sh;
                for (var xx = 0; xx <= this.xSegCount; xx++) {
                    var px = hw + xx * sw, vi = yy * (this.xSegCount + 1) + xx;
                    this.attributes.position.values[3 * vi + 'xyz'.indexOf(orient[0])] = px;
                    this.attributes.position.values[3 * vi + 'xyz'.indexOf(orient[1])] = -py;
                }
            }
            this.attributes.position.update();
        };

        /* Mesh */
        self.Mesh = function (geometry, material) {
            this.geometry = geometry; this.material = material;
            this.wireframe = false; this.attributeInstances = [];
            var e = Object.entries(geometry.attributes);
            for (var i = 0; i < e.length; i++)
                this.attributeInstances.push({ attribute: e[i][1], location: e[i][1].attach(e[i][0], material.program) });
            self.meshes.push(this);
        };
        self.Mesh.prototype.draw = function () {
            var gl2 = self.gl;
            gl2.useProgram(this.material.program);
            for (var i = 0; i < this.material.uniformInstances.length; i++) {
                var u = this.material.uniformInstances[i]; u.uniform.update(u.location);
            }
            for (var j = 0; j < this.attributeInstances.length; j++) {
                var a = this.attributeInstances[j]; a.attribute.use(a.location);
            }
            gl2.drawElements(this.wireframe ? gl2.LINES : gl2.TRIANGLES,
                this.geometry.attributes.index.values.length, gl2.UNSIGNED_SHORT, 0);
        };

        /* Common uniforms - MUST be before setSize */
        var ident = [1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1];
        self.commonUniforms = {
            projectionMatrix: new self.Uniform({ type: 'mat4', value: ident }),
            modelViewMatrix: new self.Uniform({ type: 'mat4', value: ident }),
            resolution: new self.Uniform({ type: 'vec2', value: [1, 1] }),
            aspectRatio: new self.Uniform({ type: 'float', value: 1 })
        };

        if (width && height) self.setSize(width, height);
    }

    MiniGl.prototype.setSize = function (w, h) {
        this.width = w; this.height = h;
        this.canvas.width = w; this.canvas.height = h;
        this.gl.viewport(0, 0, w, h);
        this.commonUniforms.resolution.value = [w, h];
        this.commonUniforms.aspectRatio.value = w / h;
    };
    MiniGl.prototype.setOrthographicCamera = function () {
        var zRange = Math.max(this.width, this.height) * 2;
        this.commonUniforms.projectionMatrix.value = [
            2 / this.width, 0, 0, 0, 0, 2 / this.height, 0, 0, 0, 0, 2 / (-zRange), 0, 0, 0, 0, 1
        ];
    };
    MiniGl.prototype.render = function () {
        this.gl.clearColor(0, 0, 0, 0); this.gl.clearDepth(1);
        for (var i = 0; i < this.meshes.length; i++) this.meshes[i].draw();
    };

    /* ═══════════════════════════════════════
     * Shaders
     * ═══════════════════════════════════════ */
    var shaderNoise = 'vec3 mod289(vec3 x){return x-floor(x*(1.0/289.0))*289.0;}\nvec4 mod289(vec4 x){return x-floor(x*(1.0/289.0))*289.0;}\nvec4 permute(vec4 x){return mod289(((x*34.0)+1.0)*x);}\nvec4 taylorInvSqrt(vec4 r){return 1.79284291400159-0.85373472095314*r;}\nfloat snoise(vec3 v){\nconst vec2 C=vec2(1.0/6.0,1.0/3.0);const vec4 D=vec4(0.0,0.5,1.0,2.0);\nvec3 i=floor(v+dot(v,C.yyy));vec3 x0=v-i+dot(i,C.xxx);\nvec3 g=step(x0.yzx,x0.xyz);vec3 l=1.0-g;\nvec3 i1=min(g.xyz,l.zxy);vec3 i2=max(g.xyz,l.zxy);\nvec3 x1=x0-i1+C.xxx;vec3 x2=x0-i2+C.yyy;vec3 x3=x0-D.yyy;\ni=mod289(i);\nvec4 p=permute(permute(permute(i.z+vec4(0.0,i1.z,i2.z,1.0))+i.y+vec4(0.0,i1.y,i2.y,1.0))+i.x+vec4(0.0,i1.x,i2.x,1.0));\nfloat n_=0.142857142857;vec3 ns=n_*D.wyz-D.xzx;\nvec4 j=p-49.0*floor(p*ns.z*ns.z);\nvec4 x_=floor(j*ns.z);vec4 y_=floor(j-7.0*x_);\nvec4 x=x_*ns.x+ns.yyyy;vec4 y=y_*ns.x+ns.yyyy;vec4 h=1.0-abs(x)-abs(y);\nvec4 b0=vec4(x.xy,y.xy);vec4 b1=vec4(x.zw,y.zw);\nvec4 s0=floor(b0)*2.0+1.0;vec4 s1=floor(b1)*2.0+1.0;vec4 sh=-step(h,vec4(0.0));\nvec4 a0=b0.xzyw+s0.xzyw*sh.xxyy;vec4 a1=b1.xzyw+s1.xzyw*sh.zzww;\nvec3 p0=vec3(a0.xy,h.x);vec3 p1=vec3(a0.zw,h.y);vec3 p2=vec3(a1.xy,h.z);vec3 p3=vec3(a1.zw,h.w);\nvec4 norm=taylorInvSqrt(vec4(dot(p0,p0),dot(p1,p1),dot(p2,p2),dot(p3,p3)));\np0*=norm.x;p1*=norm.y;p2*=norm.z;p3*=norm.w;\nvec4 m=max(0.6-vec4(dot(x0,x0),dot(x1,x1),dot(x2,x2),dot(x3,x3)),0.0);m=m*m;\nreturn 42.0*dot(m*m,vec4(dot(p0,x0),dot(p1,x1),dot(p2,x2),dot(p3,x3)));\n}';

    var shaderBlend = 'vec3 blendNormal(vec3 base,vec3 blend){return blend;}\nvec3 blendNormal(vec3 base,vec3 blend,float opacity){return(blendNormal(base,blend)*opacity+base*(1.0-opacity));}';

    var shaderVertex = 'varying vec3 v_color;\nvoid main(){\nfloat time=u_time*u_global.noiseSpeed;\nvec2 noiseCoord=resolution*uvNorm*u_global.noiseFreq;\nfloat tilt=resolution.y*0.6*uvNorm.y;\nfloat incline=resolution.x*uvNorm.x/2.0*u_vertDeform.incline;\nfloat offset=resolution.x/2.0*u_vertDeform.incline*mix(u_vertDeform.offsetBottom,u_vertDeform.offsetTop,uv.y);\nfloat noise=snoise(vec3(noiseCoord.x*u_vertDeform.noiseFreq.x+time*u_vertDeform.noiseFlow,noiseCoord.y*u_vertDeform.noiseFreq.y,time*u_vertDeform.noiseSpeed+u_vertDeform.noiseSeed))*u_vertDeform.noiseAmp;\nnoise*=1.0-pow(abs(uvNorm.y),2.0);\nvec3 pos=vec3(position.x,position.y+tilt+incline+noise-offset,position.z);\nv_color=u_baseColor;\nif(u_shapeStyle<0.5){\n/* WASH: even all-over blobs. Domain warp (Flow Amount) bends the colour\n   sample coords into flowing, marbled forms. Skipped when flow is 0. */\nvec2 cCoord=noiseCoord;\nif(u_flowAmount>0.0){\nvec2 wc=noiseCoord*2.0;\nfloat wt=time*u_vertDeform.noiseFlow*0.5;\nfloat wx=snoise(vec3(wc.x+13.0,wc.y+7.0,wt));\nfloat wy=snoise(vec3(wc.x+71.0,wc.y+23.0,wt+5.0));\nfloat ca=cos(u_flowAngle);\nfloat sa=sin(u_flowAngle);\ncCoord+=vec2(wx*ca-wy*sa,wx*sa+wy*ca)*u_flowAmount*0.5;\n}\nfor(int i=0;i<u_waveLayers_length;i++){\nWaveLayers layer=u_waveLayers[i];\nfloat center=(layer.noiseFloor+layer.noiseCeil)*0.5-u_spread+u_dominantBg*0.28;\nfloat halfGap=max((layer.noiseCeil-layer.noiseFloor)*0.5*u_definition,0.001);\nfloat cn=snoise(vec3(cCoord.x*layer.noiseFreq.x+time*layer.noiseFlow,cCoord.y*layer.noiseFreq.y,time*layer.noiseSpeed+layer.noiseSeed))/2.0+0.5;\nfloat n=smoothstep(center-halfGap,center+halfGap,cn);\nv_color=blendNormal(v_color,layer.color,pow(n,1.5));\n}\n}else if(u_shapeStyle<1.5){\n/* CONCENTRIC (dynamic): one or more ring-shapes drifting slowly across the\n   canvas. Within each shape the colours 2..N form a smooth radial gradient\n   that scrolls outward and loops (a colour grows from the centre to the\n   edge while the next emerges from the centre). Colour Blend sets how soft\n   the colour transitions are, Colour Repeats sets how many times the colour\n   set repeats within a shape, Shape Count sets how many separate shapes\n   appear, Radiate Speed sets the outward speed, Movement drifts the shapes\n   off and back onto the canvas, Shape Stretch elongates and breathes them,\n   Colour Spread sets size, Shape Definition sets the outer fade. */\nfloat rtime=u_time*0.000015*u_radiateSpeed;\nfloat bt=u_time*0.0003;\nfloat mt=u_time*0.0001;\nfloat nLayers=float(u_waveLayers_length);\nfloat halfTrans=mix(0.06,0.5,u_colorBlend);\nfloat ext0=max((0.7+u_spread*1.4)*(1.0-u_dominantBg*0.3),0.05);\nfloat soft=clamp(u_definition*0.6,0.05,0.95);\nvec3 acc=vec3(0.0);\nfloat wsum=0.0;\nfloat ampMax=0.0;\nfor(int s=0;s<4;s++){\nif(float(s)>=u_shapeCount)break;\nfloat fs=float(s);\nfloat r1=fract(sin(fs*43.13+1.7)*1231.43);\nfloat r2=fract(sin(fs*91.71+3.1)*9876.54);\nfloat r3=fract(sin(fs*12.37+5.9)*5432.19);\nfloat r4=fract(sin(fs*27.71+9.3)*3141.59);\nfloat sizeF=(u_shapeCount<1.5)?1.0:clamp(1.1/u_shapeCount,0.4,0.75);\nfloat ha=(fs+0.5)/u_shapeCount*6.2831+(r1-0.5)*0.9;\nfloat hr=(u_shapeCount<1.5)?0.0:mix(0.72,1.05,r2);\nvec2 home=vec2(cos(ha),sin(ha))*hr;\nvec2 drift=vec2(sin(mt*(0.7+0.5*r1)+fs*2.1),cos(mt*(0.55+0.5*r2)+fs*1.3))*u_drift;\nvec2 q=uvNorm-home-drift;\nfloat ang=u_flowAngle+((fs<0.5)?0.0:(r3-0.5)*6.2831);\nfloat ca=cos(ang);\nfloat sa=sin(ang);\nvec2 rq=vec2(q.x*ca+q.y*sa,-q.x*sa+q.y*ca);\nfloat stretchAmt=u_stretch*(0.55+0.45*sin(bt+fs));\nrq.x/=(1.0+stretchAmt*3.0);\nif(u_flowAmount>0.0){\nvec2 wc=q*1.2;\nfloat ft=time*u_vertDeform.noiseFlow*0.5;\nfloat wx=snoise(vec3(wc.x+13.0,wc.y+7.0,ft+fs));\nfloat wy=snoise(vec3(wc.x+71.0,wc.y+23.0,ft+5.0+fs));\nrq+=vec2(wx,wy)*u_flowAmount*0.6;\n}\nfloat extent=ext0*sizeF*mix(0.85,1.15,r4)*(1.0+0.12*sin(bt*0.85+fs));\nfloat rNorm=length(rq)/extent;\nfloat cyc=fract(rNorm*u_ringCount-rtime*(0.8+0.4*r1)+r2);\nfloat scaled=cyc*nLayers;\nfloat idx=floor(scaled);\nfloat idxB=mod(idx+1.0,nLayers);\nfloat tt=fract(scaled);\ntt=smoothstep(0.5-halfTrans,0.5+halfTrans,tt);\nvec3 colA=u_waveLayers[0].color;\nvec3 colB=u_waveLayers[0].color;\nfor(int i=0;i<u_waveLayers_length;i++){\nfloat fi=float(i);\nif(abs(fi-idx)<0.5)colA=u_waveLayers[i].color;\nif(abs(fi-idxB)<0.5)colB=u_waveLayers[i].color;\n}\nvec3 ring=mix(colA,colB,tt);\nfloat amp=1.0-smoothstep(1.0-soft,1.0+soft,rNorm);\nacc+=ring*amp;\nwsum+=amp;\nampMax=max(ampMax,amp);\n}\nvec3 blended=(wsum>0.0001)?(acc/wsum):u_baseColor;\nv_color=mix(u_baseColor,blended,ampMax);\n}else{\n/* BANDS: a wavy perspective fan whose colours radiate from the centre of\n   each band, separated by and glowing into a dark Background Colour. See\n   the block below for the controls. */\nfloat nLayersB=float(u_waveLayers_length);\nfloat pal=nLayersB+1.0;\n/* BANDS (perspective fan + radiating colours): the bands are wavy wedges\n   about a pivot off to one side (perspective), separated by the dark\n   Background Colour. Within each band the colours radiate out from the\n   centre-line to the edges and animate over time, and each band glows\n   softly into the gap beside it. Flow Angle rotates the fan, Perspective\n   the fan strength, Waviness the undulation, Band Thickness the spacing,\n   Colour Repeats the colour steps, Colour Blend the softness, Radiate\n   Speed the radiate. */\nfloat ar=max(aspectRatio,0.0001);\nfloat fa=u_flowAngle*0.0174533;\nfloat bca=cos(fa);\nfloat bsa=sin(fa);\nvec2 Pp=vec2(uvNorm.x*ar,uvNorm.y);\nvec2 Pr=vec2(Pp.x*bca+Pp.y*bsa,-Pp.x*bsa+Pp.y*bca);\nfloat persp=mix(7.0,1.4,clamp(u_bandVary,0.0,1.0));\nvec2 q=Pr-vec2(-persp-ar,0.0);\nfloat dist=length(q);\nfloat ang=atan(q.y,q.x);\nfloat coord=ang*(persp+ar)*3.0;\nfloat wav=mix(0.0,1.1,u_bandMax);\nif(wav>0.0){\nfloat wt=u_time*0.0001;\ncoord+=snoise(vec3(dist*0.8,ang*5.0,wt))*wav;\ncoord+=0.5*snoise(vec3(dist*1.7+5.0,ang*9.0,wt*1.3))*wav;\n}\nfloat halfTransB=mix(0.06,0.5,u_colorBlend);\nfloat cellIdx=floor(coord);\nfloat localT=fract(coord);\nfloat d=abs(localT-0.5)*2.0;\nfloat thick=mix(0.95,0.4,u_bandMin);\nfloat r=clamp(d/thick,0.0,1.0);\nfloat radin=u_time*0.00004*u_radiateSpeed;\nfloat cphase=r*u_ringCount-radin+cellIdx*0.37;\nfloat idx=floor(cphase);\nfloat cblend=smoothstep(0.5-halfTransB,0.5+halfTransB,fract(cphase));\nfloat iA=mod(idx,pal);\nif(iA<0.0)iA+=pal;\nfloat iB=mod(idx+1.0,pal);\nif(iB<0.0)iB+=pal;\nvec3 colC=u_baseColor;\nvec3 colD=u_baseColor;\nfor(int i=0;i<u_waveLayers_length;i++){\nfloat slot=float(i)+1.0;\nif(abs(slot-iA)<0.5)colC=u_waveLayers[i].color;\nif(abs(slot-iB)<0.5)colD=u_waveLayers[i].color;\n}\nvec3 radCol=mix(colC,colD,cblend);\nfloat body=1.0-smoothstep(thick*0.7,thick,d);\nfloat glow=1.0-smoothstep(thick,min(thick+0.45,1.2),d);\nvec3 col=mix(u_bandBgColor,radCol,body);\ncol+=radCol*glow*0.35;\nv_color=clamp(col,0.0,1.0);\n}\n/* Satin sheen: a slow, flowing brightness variation that catches the\n   light along the colours (has least effect where colour 1 is dark). */\nif(u_sheen>0.0){\nfloat lite=snoise(vec3(uvNorm.x*2.5+time*0.05,uvNorm.y*2.5,time*0.1+11.0));\nv_color*=clamp(1.0+lite*u_sheen,0.0,2.5);\n}\ngl_Position=projectionMatrix*modelViewMatrix*vec4(pos,1.0);\n}';

    var shaderFragment = 'varying vec3 v_color;\nvoid main(){\nvec3 color=v_color;\nif(u_darken_top==1.0){vec2 st=gl_FragCoord.xy/resolution.xy;color.g-=pow(st.y+sin(-12.0)*st.x,u_shadow_power)*0.4;}\nif(u_grain>0.0){\nfloat gn=fract(sin(dot(gl_FragCoord.xy+u_time*0.0015,vec2(12.9898,78.233)))*43758.5453);\ncolor+=(gn-0.5)*u_grain;\n}\ngl_FragColor=vec4(color,1.0);\n}';

    /* ── Glass refraction (second pass) shaders ──
     * A full-screen quad samples the rendered gradient texture with a
     * displacement offset. Type 1 (Liquid) uses simplex noise on its own
     * seed so the ripples move independently of the colour animation.
     * Type 2 (Fluted) models an array of cylindrical-lens ribs rotated to a
     * precise angle: each rib bends the gradient with a per-rib sawtooth
     * ramp and is shaded as a rounded glass rod, for true reeded glass. */
    var shaderRefractVertex = 'precision highp float;\nattribute vec2 a_pos;\nvarying vec2 v_uv;\nvoid main(){v_uv=a_pos*0.5+0.5;gl_Position=vec4(a_pos,0.0,1.0);}';

    var shaderRefractMain = 'varying vec2 v_uv;\nuniform sampler2D u_texture;\nuniform float u_aspect;\nuniform float u_time;\nuniform int u_glassType;\nuniform float u_strength;\nuniform float u_scale;\nuniform float u_rippleSpeed;\nuniform float u_ribCount;\nuniform float u_ribAngle;\nuniform float u_ribSharp;\nuniform float u_hiWidth;\nuniform float u_hiStr;\nuniform float u_shWidth;\nuniform float u_shStr;\nuniform float u_seed;\nvoid main(){\nvec2 uv=v_uv;\nvec2 disp=vec2(0.0);\nfloat gain=1.0;\nif(u_glassType==1){\nfloat freq=mix(18.0,1.5,clamp((u_scale-1.0)/49.0,0.0,1.0));\nvec2 nc=vec2(uv.x*u_aspect,uv.y)*freq;\nfloat t=u_time*u_rippleSpeed*0.00005;\nfloat nx=snoise(vec3(nc+u_seed,t));\nfloat ny=snoise(vec3(nc+u_seed+37.3,t+19.1));\ndisp=vec2(nx,ny)*u_strength;\n}else if(u_glassType==2){\n/* Fluted / reeded glass: an array of vertical cylindrical-lens ribs.\n   Within each rib the gradient is swept by a sawtooth ramp, so a\n   compressed slice of the colours repeats per rib (as when looking\n   through real reeded glass), and each rib is shaded like a rounded\n   glass rod lit from the right: a shadow on the left side of each flute\n   fading rightward, and a highlight on the right side fading leftward,\n   set by independent Width + Strength sliders (Width is how far the band\n   reaches across the flute, so Shadow Width 100% fades all the way from\n   the left edge to the right). Rib Sharpness morphs the lens (flute)\n   profile from a soft, round flute toward a flat-faceted prism. The\n   shading is purely multiplicative, so it scales with whatever is behind\n   the glass: over black it stays black and the ribs disappear, and the\n   highlight only brightens where there is real colour to catch. With\n   both strengths at 0 there is no shading line at all. */\nvec2 p=vec2((uv.x-0.5)*u_aspect,uv.y-0.5);\nfloat ca=cos(u_ribAngle);\nfloat sa=sin(u_ribAngle);\nfloat coord=p.x*sa+p.y*ca;\nvec2 dir=vec2(sa,ca);\nfloat sharp=clamp(u_ribSharp,0.0,1.0);\nfloat local=fract(coord*u_ribCount)*2.0-1.0;\nfloat gamma=mix(1.7,1.0,sharp);\nfloat lens=sign(local)*pow(abs(local),gamma);\nvec2 d=dir*(-lens)*u_strength;\ndisp=vec2(d.x/u_aspect,d.y);\nfloat t=local*0.5+0.5;\nfloat dk=(1.0-smoothstep(0.0,u_shWidth,t))*u_shStr;\nfloat lt=smoothstep(1.0-u_hiWidth,1.0,t)*u_hiStr;\ngain=(1.0-dk)*(1.0+lt);\n}\ngl_FragColor=vec4(clamp(texture2D(u_texture,clamp(uv+disp,0.0,1.0)).rgb*gain,0.0,1.0),1.0);\n}';

    /* ═══════════════════════════════════════
     * KDNAGradient - WebGL
     * ═══════════════════════════════════════ */
    function KDNAGradient(config) {
        this.config = config;
        this.playing = false;
        this.t = 1253106;
        this.last = 0;
        this.raf = null;
    }

    KDNAGradient.prototype.init = function (canvas) {
        var self = this;
        var cfg = self.config;
        self.canvas = canvas;

        /* Get dimensions from wrapper (which has 100% width/height of container) */
        var parent = canvas.parentElement;
        var w = parent.offsetWidth;
        var h = parent.offsetHeight;

        /* Safety: minimum dimensions */
        if (w < 10) w = 300;
        if (h < 10) h = 200;

        var dpr = Math.min(window.devicePixelRatio || 1, 2);
        var pw = Math.round(w * dpr);
        var ph = Math.round(h * dpr);

        self.minigl = new MiniGl(canvas, pw, ph);

        /* Cap canvas to GPU viewport limits so tall/wide containers
           don't exceed the renderbuffer and render only partially.
           CSS width/height:100% stretches the capped canvas to fill. */
        var maxDims = self.minigl.gl.getParameter(self.minigl.gl.MAX_VIEWPORT_DIMS);
        self._maxDims = maxDims || [8192, 8192];
        if (pw > self._maxDims[0] || ph > self._maxDims[1]) {
            pw = Math.min(pw, self._maxDims[0]);
            ph = Math.min(ph, self._maxDims[1]);
            self.minigl.setSize(pw, ph);
        }

        canvas.style.width = '100%';
        canvas.style.height = '100%';
        self.minigl.setOrthographicCamera();

        var colors = [];
        for (var i = 0; i < cfg.colours.length; i++) colors.push(hexToNorm(cfg.colours[i]));

        /* Bands background colour: the canvas fill behind the bands. Falls
           back to colour 1 when not set, so other styles are unaffected. */
        var bandBgColor = cfg.bandBgColor ? hexToNorm(cfg.bandBgColor) : (colors[0] || [0, 0, 0]);

        /*
         * Map user-facing values to shader-friendly values:
         * - Speed 1-20 maps to noiseSpeed (animation rate)
         * - Amplitude 50-800 maps to noiseAmp (vertex displacement)
         *   The raw value is WAY too high for the shader, so we scale it
         *   down significantly. User "240" becomes ~24 in the shader.
         */
        var noiseSpeed = (cfg.speed || 5) * 1e-6;
        var shaderAmp = (cfg.amplitude || 320) * 0.1;

        /*
         * Shape controls (Session 7). Defaults are chosen so the shader
         * reproduces the original even-wash look exactly:
         * - flowAmount 0   -> no domain warp (round blobs)
         * - definition 40  -> gap multiplier 1.0 (original smoothstep gap)
         * - spread 50      -> threshold bias 0 (original colour coverage)
         */
        var flowAmount = (cfg.flowAmount != null ? cfg.flowAmount : 0) / 100;
        var flowAngle = (cfg.flowAngle || 0) * Math.PI / 180;
        var defSlider = cfg.definition != null ? cfg.definition : 40;
        var definitionMul = Math.pow(2, (40 - defSlider) / 18);
        var spreadSlider = cfg.spread != null ? cfg.spread : 50;
        var spreadBias = (spreadSlider - 50) / 100 * 0.7;

        /*
         * Shape style: 'wash' (default) keeps the all-over blob blending.
         * 'concentric' keeps colour 1 as the predominant dark background and
         * paints colours 2..N as an elongated, flow-warped radial ramp
         * (inner ring to outer ring) that fades back into the base, for the
         * moody billboard look. Stretch elongates the rings along Flow Angle.
         */
        var shapeStyle = cfg.shapeStyle === 'concentric' ? 1 : ( cfg.shapeStyle === 'bands' ? 2 : 0 );
        var stretch = (cfg.stretch != null ? cfg.stretch : 0) / 100;

        /* Dominant Background: when on, colour 1 takes over most of the
           canvas (raises the colour threshold in wash, tightens the rings
           in concentric). Off by default so nothing changes unless used. */
        var dominantBg = cfg.dominantBg ? 1 : 0;

        /* Concentric dynamics. ringCount = how many times the colour set
           repeats within one shape; shapeCount = how many separate shapes
           appear on the canvas; colorBlend = softness of the colour
           transitions (0 = defined rings, 1 = very soft); drift = how far
           the shapes wander off and back onto the canvas; radiateSpeed =
           how fast the colours travel outward. */
        var radiateSpeed = cfg.radiateSpeed != null ? cfg.radiateSpeed : 45;
        var ringCount = cfg.ringCount != null ? cfg.ringCount : 1;
        var shapeCount = cfg.shapeCount != null ? cfg.shapeCount : 1;
        var colorBlend = (cfg.colorBlend != null ? cfg.colorBlend : 70) / 100;
        var drift = (cfg.drift != null ? cfg.drift : 40) / 100 * 1.5;

        /* Bands width range: each band is a random width between Min and Max
           (normalised 0..1, expanded to screen fractions in the shader).
           bandVary = how much each band pinches/swells in width along its
           own length (0 = even width, 1 = strong fabric-fold tapering). */
        var bandMin = (cfg.bandMin != null ? cfg.bandMin : 25) / 100;
        var bandMax = (cfg.bandMax != null ? cfg.bandMax : 60) / 100;
        var bandVary = (cfg.bandVary != null ? cfg.bandVary : 50) / 100;

        /* Texture: subtle film grain (per-pixel speckle) and a satin sheen
           (slow brightness variation that catches the light along the
           colours). Both default to 0 (off) so existing presets are
           unchanged. */
        var grain = (cfg.grain != null ? cfg.grain : 0) / 100;
        var sheen = (cfg.sheen != null ? cfg.sheen : 0) / 100;

        var uniforms = {
            u_time: new self.minigl.Uniform({ value: 0 }),
            u_shadow_power: new self.minigl.Uniform({ value: w < 600 ? 5 : 6 }),
            u_darken_top: new self.minigl.Uniform({ value: cfg.darkenTop ? 1 : 0 }),
            u_global: new self.minigl.Uniform({
                value: {
                    noiseFreq: new self.minigl.Uniform({ value: [14e-5, 29e-5], type: 'vec2' }),
                    noiseSpeed: new self.minigl.Uniform({ value: noiseSpeed })
                }, type: 'struct'
            }),
            u_vertDeform: new self.minigl.Uniform({
                value: {
                    incline: new self.minigl.Uniform({ value: 0 }),
                    offsetTop: new self.minigl.Uniform({ value: -0.5 }),
                    offsetBottom: new self.minigl.Uniform({ value: -0.5 }),
                    noiseFreq: new self.minigl.Uniform({ value: [0.8, 1.2], type: 'vec2' }),
                    noiseAmp: new self.minigl.Uniform({ value: shaderAmp }),
                    noiseSpeed: new self.minigl.Uniform({ value: 10 }),
                    noiseFlow: new self.minigl.Uniform({ value: 3 }),
                    noiseSeed: new self.minigl.Uniform({ value: cfg.seed || 5 })
                }, type: 'struct', excludeFrom: 'fragment'
            }),
            u_baseColor: new self.minigl.Uniform({ value: colors[0] || [0, 0, 0], type: 'vec3', excludeFrom: 'fragment' }),
            u_bandBgColor: new self.minigl.Uniform({ value: bandBgColor, type: 'vec3', excludeFrom: 'fragment' }),
            u_waveLayers: new self.minigl.Uniform({ value: [], excludeFrom: 'fragment', type: 'array' }),
            u_flowAmount: new self.minigl.Uniform({ value: flowAmount, excludeFrom: 'fragment' }),
            u_flowAngle: new self.minigl.Uniform({ value: flowAngle, excludeFrom: 'fragment' }),
            u_definition: new self.minigl.Uniform({ value: definitionMul, excludeFrom: 'fragment' }),
            u_spread: new self.minigl.Uniform({ value: spreadBias, excludeFrom: 'fragment' }),
            u_shapeStyle: new self.minigl.Uniform({ value: shapeStyle, excludeFrom: 'fragment' }),
            u_stretch: new self.minigl.Uniform({ value: stretch, excludeFrom: 'fragment' }),
            u_dominantBg: new self.minigl.Uniform({ value: dominantBg, excludeFrom: 'fragment' }),
            u_radiateSpeed: new self.minigl.Uniform({ value: radiateSpeed, excludeFrom: 'fragment' }),
            u_ringCount: new self.minigl.Uniform({ value: ringCount, excludeFrom: 'fragment' }),
            u_shapeCount: new self.minigl.Uniform({ value: shapeCount, excludeFrom: 'fragment' }),
            u_colorBlend: new self.minigl.Uniform({ value: colorBlend, excludeFrom: 'fragment' }),
            u_drift: new self.minigl.Uniform({ value: drift, excludeFrom: 'fragment' }),
            u_bandMin: new self.minigl.Uniform({ value: bandMin, excludeFrom: 'fragment' }),
            u_bandMax: new self.minigl.Uniform({ value: bandMax, excludeFrom: 'fragment' }),
            u_bandVary: new self.minigl.Uniform({ value: bandVary, excludeFrom: 'fragment' }),
            u_bandSeed: new self.minigl.Uniform({ value: cfg.seed || 5, excludeFrom: 'fragment' }),
            u_sheen: new self.minigl.Uniform({ value: sheen, excludeFrom: 'fragment' }),
            u_grain: new self.minigl.Uniform({ value: grain, excludeFrom: 'vertex' })
        };

        var maxLayers = Math.min(colors.length - 1, 9);
        for (var c = 1; c <= maxLayers; c++) {
            uniforms.u_waveLayers.value.push(new self.minigl.Uniform({
                value: {
                    color: new self.minigl.Uniform({ value: colors[c], type: 'vec3' }),
                    noiseFreq: new self.minigl.Uniform({ value: [0.8 + c * 0.08, 0.9 + c * 0.08], type: 'vec2' }),
                    noiseSpeed: new self.minigl.Uniform({ value: 11 + 0.3 * c }),
                    noiseFlow: new self.minigl.Uniform({ value: 6.5 + 0.3 * c }),
                    noiseSeed: new self.minigl.Uniform({ value: (cfg.seed || 5) + 10 * c }),
                    noiseFloor: new self.minigl.Uniform({ value: 0.1 }),
                    noiseCeil: new self.minigl.Uniform({ value: 0.63 + 0.035 * c })
                }, type: 'struct'
            }));
        }

        var vertexSrc = shaderNoise + '\n\n' + shaderBlend + '\n\n' + shaderVertex;
        self.material = new self.minigl.Material(vertexSrc, shaderFragment, uniforms);

        /* Density: higher multiplier = more mesh segments = smoother gradients */
        var densityMul = (cfg.density || 6) * 0.004;
        var xSeg = Math.max(12, Math.ceil(pw * densityMul));
        var ySeg = Math.max(12, Math.ceil(ph * densityMul));

        self.geometry = new self.minigl.PlaneGeometry();
        self.geometry.setTopology(xSeg, ySeg);
        self.geometry.setSize(pw, ph);
        self.mesh = new self.minigl.Mesh(self.geometry, self.material);
        self.uniforms = uniforms;
        self.densityMul = densityMul;

        /* Optional second pass: glass refraction. Skipped entirely when
           the glass type is None or the refraction strength is 0, so there
           is zero performance cost when the effect is off. */
        self._initRefraction(cfg, pw, ph);

        /* Render first frame synchronously so the canvas is never blank */
        self.uniforms.u_time.value = self.t;
        self.renderFrame();

        /* Resize handler (ResizeObserver catches CSS/Elementor breakpoint
           changes, window resize catches browser chrome and DPR changes) */
        self._lastW = w; self._lastH = h;
        self._onResize = function () {
            var nw = parent.offsetWidth || 300;
            var nh = parent.offsetHeight || 200;
            if (nw === self._lastW && nh === self._lastH) return;
            self._lastW = nw; self._lastH = nh;
            var nd = Math.min(window.devicePixelRatio || 1, 2);
            var npw = Math.min(Math.round(nw * nd), self._maxDims[0]);
            var nph = Math.min(Math.round(nh * nd), self._maxDims[1]);
            self.minigl.setSize(npw, nph);
            self.minigl.setOrthographicCamera();
            var nx = Math.max(12, Math.ceil(npw * self.densityMul));
            var ny = Math.max(12, Math.ceil(nph * self.densityMul));
            self.mesh.geometry.setTopology(nx, ny);
            self.mesh.geometry.setSize(npw, nph);
            self.uniforms.u_shadow_power.value = nw < 600 ? 5 : 6;
            if (self.refractActive) self._resizeRefraction(npw, nph);
        };
        if ('ResizeObserver' in window) {
            self._resizeObs = new ResizeObserver(self._onResize);
            self._resizeObs.observe(parent);
        }
        window.addEventListener('resize', self._onResize);

        self.play();
    };

    KDNAGradient.prototype.animate = function (ts) {
        var self = this;
        if (!self.playing) return;
        if (document.hidden) { self.raf = requestAnimationFrame(function (t) { self.animate(t); }); return; }
        self.t += Math.min(ts - self.last, 1000 / 15);
        self.last = ts;
        self.uniforms.u_time.value = self.t;
        self.renderFrame();
        self.raf = requestAnimationFrame(function (t) { self.animate(t); });
    };

    KDNAGradient.prototype.play = function () {
        this.playing = true;
        var self = this;
        self.raf = requestAnimationFrame(function (t) { self.animate(t); });
    };
    KDNAGradient.prototype.pause = function () {
        this.playing = false;
        if (this.raf) { cancelAnimationFrame(this.raf); this.raf = null; }
    };
    KDNAGradient.prototype.destroy = function () {
        this.pause();
        if (this._resizeObs) this._resizeObs.disconnect();
        if (this._onResize) window.removeEventListener('resize', this._onResize);
        if (this.refractActive && this.minigl) {
            var gl = this.minigl.gl;
            if (this._fbo) gl.deleteFramebuffer(this._fbo);
            if (this._fboTex) gl.deleteTexture(this._fboTex);
            if (this._quadBuf) gl.deleteBuffer(this._quadBuf);
            if (this._postProg) gl.deleteProgram(this._postProg);
            this.refractActive = false;
        }
    };

    /* ── Glass refraction second pass ── */

    /**
     * Set up the off-screen framebuffer and the refraction program.
     * Does nothing (and leaves refractActive false) when the effect is off,
     * so renderFrame falls straight through to a single direct draw.
     */
    KDNAGradient.prototype._initRefraction = function (cfg, pw, ph) {
        var self = this;
        var type = cfg.glassType || 'none';
        var strength = parseFloat(cfg.refractStrength) || 0;

        self.refractActive = ( type === 'liquid' || type === 'fluted' ) && strength > 0;
        if (!self.refractActive) return;

        var gl = self.minigl.gl;
        self._glTypeNum = type === 'liquid' ? 1 : 2;

        /* Map admin values to shader-friendly values */
        self._rUniforms = {
            strength:    strength * 0.0015,
            scale:       parseFloat(cfg.refractScale) || 12,
            rippleSpeed: parseFloat(cfg.refractSpeed) || 5,
            ribCount:    parseFloat(cfg.ribCount) || 40,
            ribAngle:    (parseFloat(cfg.ribAngle) || 0) * Math.PI / 180,
            ribSharp:    (cfg.ribSharp != null ? parseFloat(cfg.ribSharp) : 0) / 100,
            /* Rib shading: directional, lit from the right. Width is how far
               the band reaches across the flute (0.05 = a thin edge band,
               1.0 = the full flute width); Strength is its intensity. Shadow
               sits on the left edge fading right, highlight on the right edge
               fading left. Multiplicative, so it stays invisible over black. */
            hiWidth:     mix01(0.05, 1.0, (cfg.ribHiWidth != null ? parseFloat(cfg.ribHiWidth) : 25) / 100),
            hiStr:       (cfg.ribHiStrength != null ? parseFloat(cfg.ribHiStrength) : 40) / 100 * 0.6,
            shWidth:     mix01(0.05, 1.0, (cfg.ribShWidth != null ? parseFloat(cfg.ribShWidth) : 50) / 100),
            shStr:       (cfg.ribShStrength != null ? parseFloat(cfg.ribShStrength) : 60) / 100 * 0.9,
            seed:        (cfg.seed || 5) * 3.7
        };

        /* Compile the post-processing program */
        function compile(glType, src) {
            var s = gl.createShader(glType);
            gl.shaderSource(s, src); gl.compileShader(s);
            if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) console.error('KDNA Refract Shader:', gl.getShaderInfoLog(s));
            return s;
        }
        var fragSrc = 'precision highp float;\n' + shaderNoise + '\n' + shaderRefractMain;
        var vsh = compile(gl.VERTEX_SHADER, shaderRefractVertex);
        var fsh = compile(gl.FRAGMENT_SHADER, fragSrc);
        var prog = gl.createProgram();
        gl.attachShader(prog, vsh); gl.attachShader(prog, fsh);
        gl.linkProgram(prog);
        if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) console.error('KDNA Refract Program:', gl.getProgramInfoLog(prog));
        self._postProg = prog;
        self._postLoc = {
            pos:         gl.getAttribLocation(prog, 'a_pos'),
            texture:     gl.getUniformLocation(prog, 'u_texture'),
            aspect:      gl.getUniformLocation(prog, 'u_aspect'),
            time:        gl.getUniformLocation(prog, 'u_time'),
            glassType:   gl.getUniformLocation(prog, 'u_glassType'),
            strength:    gl.getUniformLocation(prog, 'u_strength'),
            scale:       gl.getUniformLocation(prog, 'u_scale'),
            rippleSpeed: gl.getUniformLocation(prog, 'u_rippleSpeed'),
            ribCount:    gl.getUniformLocation(prog, 'u_ribCount'),
            ribAngle:    gl.getUniformLocation(prog, 'u_ribAngle'),
            ribSharp:    gl.getUniformLocation(prog, 'u_ribSharp'),
            hiWidth:     gl.getUniformLocation(prog, 'u_hiWidth'),
            hiStr:       gl.getUniformLocation(prog, 'u_hiStr'),
            shWidth:     gl.getUniformLocation(prog, 'u_shWidth'),
            shStr:       gl.getUniformLocation(prog, 'u_shStr'),
            seed:        gl.getUniformLocation(prog, 'u_seed')
        };

        /* Full-screen quad (triangle strip) in clip space */
        self._quadBuf = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, self._quadBuf);
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);

        /* Off-screen target the gradient renders into */
        self._fboTex = gl.createTexture();
        self._fbo = gl.createFramebuffer();
        self._resizeRefraction(pw, ph);
    };

    /**
     * (Re)allocate the framebuffer texture at the given pixel size.
     * CLAMP_TO_EDGE means displaced samples near the edge extend the border
     * colour rather than showing a transparent gap.
     */
    KDNAGradient.prototype._resizeRefraction = function (pw, ph) {
        var self = this, gl = self.minigl.gl;
        self._postW = pw; self._postH = ph;
        gl.bindTexture(gl.TEXTURE_2D, self._fboTex);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, pw, ph, 0, gl.RGBA, gl.UNSIGNED_BYTE, null);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        gl.bindFramebuffer(gl.FRAMEBUFFER, self._fbo);
        gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, self._fboTex, 0);
        gl.bindFramebuffer(gl.FRAMEBUFFER, null);
    };

    /**
     * Draw one frame. With refraction off this is a single direct render
     * (identical to before). With it on, the gradient is rendered to the
     * framebuffer texture and then warped onto the canvas by the quad.
     */
    KDNAGradient.prototype.renderFrame = function () {
        var self = this;
        if (!self.refractActive) { self.minigl.render(); return; }
        var gl = self.minigl.gl;
        gl.bindFramebuffer(gl.FRAMEBUFFER, self._fbo);
        gl.viewport(0, 0, self._postW, self._postH);
        self.minigl.render();
        gl.bindFramebuffer(gl.FRAMEBUFFER, null);
        gl.viewport(0, 0, self._postW, self._postH);

        var p = self._postProg, u = self._postLoc, r = self._rUniforms;
        gl.useProgram(p);
        gl.activeTexture(gl.TEXTURE0);
        gl.bindTexture(gl.TEXTURE_2D, self._fboTex);
        gl.uniform1i(u.texture, 0);
        gl.uniform1f(u.aspect, self._postW / self._postH);
        gl.uniform1f(u.time, self.t);
        gl.uniform1i(u.glassType, self._glTypeNum);
        gl.uniform1f(u.strength, r.strength);
        gl.uniform1f(u.scale, r.scale);
        gl.uniform1f(u.rippleSpeed, r.rippleSpeed);
        gl.uniform1f(u.ribCount, r.ribCount);
        gl.uniform1f(u.ribAngle, r.ribAngle);
        gl.uniform1f(u.ribSharp, r.ribSharp);
        gl.uniform1f(u.hiWidth, r.hiWidth);
        gl.uniform1f(u.hiStr, r.hiStr);
        gl.uniform1f(u.shWidth, r.shWidth);
        gl.uniform1f(u.shStr, r.shStr);
        gl.uniform1f(u.seed, r.seed);
        gl.bindBuffer(gl.ARRAY_BUFFER, self._quadBuf);
        gl.enableVertexAttribArray(u.pos);
        gl.vertexAttribPointer(u.pos, 2, gl.FLOAT, false, 0, 0);
        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    };

    /* ═══════════════════════════════════════
     * Canvas 2D Fallback
     * Refraction is gracefully skipped here: the moving blobs already
     * approximate the gradient, and a per-pixel warp is not affordable
     * without WebGL. Browsers without WebGL are rare and low-powered.
     * ═══════════════════════════════════════ */
    function KDNAGradientFallback(config) {
        this.config = config; this.playing = false; this.t = 0; this.raf = null;
    }

    KDNAGradientFallback.prototype.init = function (canvas) {
        var self = this, cfg = self.config;
        self.canvas = canvas;
        self.ctx = canvas.getContext('2d');
        self.colors = cfg.colours.slice();
        self.speed = (cfg.speed || 5) * 0.0005;
        self.blobs = [];
        for (var b = 0; b < self.colors.length; b++) {
            self.blobs.push({
                x: Math.random(), y: Math.random(),
                vx: (Math.random() - 0.5) * 0.3, vy: (Math.random() - 0.5) * 0.3,
                radius: 0.5 + Math.random() * 0.5, color: self.colors[b]
            });
        }
        var parent = canvas.parentElement;
        var lastFbW = 0, lastFbH = 0;
        self._onResize = function () {
            var d = Math.min(window.devicePixelRatio || 1, 2);
            var w = parent.offsetWidth || 300, h = parent.offsetHeight || 200;
            if (w === lastFbW && h === lastFbH) return;
            lastFbW = w; lastFbH = h;
            canvas.width = Math.round(w * d); canvas.height = Math.round(h * d);
            canvas.style.width = '100%'; canvas.style.height = '100%';
        };
        self._onResize();
        lastFbW = parent.offsetWidth || 300; lastFbH = parent.offsetHeight || 200;
        if ('ResizeObserver' in window) {
            self._resizeObs = new ResizeObserver(self._onResize);
            self._resizeObs.observe(parent);
        }
        window.addEventListener('resize', self._onResize);
        self.play();
    };

    KDNAGradientFallback.prototype.animate = function () {
        var self = this;
        if (!self.playing) return;
        var w = self.canvas.width, h = self.canvas.height, ctx = self.ctx, dt = self.speed;
        ctx.globalCompositeOperation = 'source-over';
        ctx.fillStyle = self.colors[0] || '#000000';
        ctx.fillRect(0, 0, w, h);
        for (var i = 0; i < self.blobs.length; i++) {
            var b = self.blobs[i];
            b.x += b.vx * dt; b.y += b.vy * dt;
            if (b.x < -0.2 || b.x > 1.2) b.vx *= -1;
            if (b.y < -0.2 || b.y > 1.2) b.vy *= -1;
            var cx = b.x * w, cy = b.y * h, r = b.radius * Math.max(w, h);
            var grad = ctx.createRadialGradient(cx, cy, 0, cx, cy, r);
            grad.addColorStop(0, b.color + 'bb'); grad.addColorStop(1, b.color + '00');
            ctx.fillStyle = grad; ctx.fillRect(0, 0, w, h);
        }
        self.t += dt;
        self.raf = requestAnimationFrame(function () { self.animate(); });
    };

    KDNAGradientFallback.prototype.play = function () {
        this.playing = true; var self = this;
        self.raf = requestAnimationFrame(function () { self.animate(); });
    };
    KDNAGradientFallback.prototype.pause = function () {
        this.playing = false;
        if (this.raf) { cancelAnimationFrame(this.raf); this.raf = null; }
    };
    KDNAGradientFallback.prototype.destroy = function () {
        this.pause();
        if (this._resizeObs) this._resizeObs.disconnect();
        if (this._onResize) window.removeEventListener('resize', this._onResize);
    };

    /* ═══════════════════════════════════════
     * Factory
     * ═══════════════════════════════════════ */
    window.KDNAGradientEngine = {
        create: function (config) {
            if (supportsWebGL()) return new KDNAGradient(config);
            return new KDNAGradientFallback(config);
        }
    };

})(window);
