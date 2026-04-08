/**
 * KDNA Gradient Engine v1.0.2
 * WebGL mesh gradient with Canvas 2D fallback.
 */

(function (window) {
    'use strict';

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
        this.commonUniforms.projectionMatrix.value = [
            2 / this.width, 0, 0, 0, 0, 2 / this.height, 0, 0, 0, 0, 2 / (-4000), 0, 0, 0, 0, 1
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

    var shaderVertex = 'varying vec3 v_color;\nvoid main(){\nfloat time=u_time*u_global.noiseSpeed;\nvec2 noiseCoord=resolution*uvNorm*u_global.noiseFreq;\nfloat tilt=resolution.y*0.6*uvNorm.y;\nfloat incline=resolution.x*uvNorm.x/2.0*u_vertDeform.incline;\nfloat offset=resolution.x/2.0*u_vertDeform.incline*mix(u_vertDeform.offsetBottom,u_vertDeform.offsetTop,uv.y);\nfloat noise=snoise(vec3(noiseCoord.x*u_vertDeform.noiseFreq.x+time*u_vertDeform.noiseFlow,noiseCoord.y*u_vertDeform.noiseFreq.y,time*u_vertDeform.noiseSpeed+u_vertDeform.noiseSeed))*u_vertDeform.noiseAmp;\nnoise*=1.0-pow(abs(uvNorm.y),2.0);\nvec3 pos=vec3(position.x,position.y+tilt+incline+noise-offset,position.z);\nv_color=u_baseColor;\nfor(int i=0;i<u_waveLayers_length;i++){\nWaveLayers layer=u_waveLayers[i];\nfloat n=smoothstep(layer.noiseFloor,layer.noiseCeil,snoise(vec3(noiseCoord.x*layer.noiseFreq.x+time*layer.noiseFlow,noiseCoord.y*layer.noiseFreq.y,time*layer.noiseSpeed+layer.noiseSeed))/2.0+0.5);\nv_color=blendNormal(v_color,layer.color,pow(n,1.5));\n}\ngl_Position=projectionMatrix*modelViewMatrix*vec4(pos,1.0);\n}';

    var shaderFragment = 'varying vec3 v_color;\nvoid main(){\nvec3 color=v_color;\nif(u_darken_top==1.0){vec2 st=gl_FragCoord.xy/resolution.xy;color.g-=pow(st.y+sin(-12.0)*st.x,u_shadow_power)*0.4;}\ngl_FragColor=vec4(color,1.0);\n}';

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
        canvas.style.width = '100%';
        canvas.style.height = '100%';
        self.minigl.setOrthographicCamera();

        var colors = [];
        for (var i = 0; i < cfg.colours.length; i++) colors.push(hexToNorm(cfg.colours[i]));

        /*
         * Map user-facing values to shader-friendly values:
         * - Speed 1-20 maps to noiseSpeed (animation rate)
         * - Amplitude 50-800 maps to noiseAmp (vertex displacement)
         *   The raw value is WAY too high for the shader, so we scale it
         *   down significantly. User "240" becomes ~24 in the shader.
         */
        var noiseSpeed = (cfg.speed || 5) * 1e-6;
        var shaderAmp = (cfg.amplitude || 320) * 0.1;

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
            u_waveLayers: new self.minigl.Uniform({ value: [], excludeFrom: 'fragment', type: 'array' })
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

        /* Resize handler */
        self._onResize = function () {
            var nw = parent.offsetWidth || 300;
            var nh = parent.offsetHeight || 200;
            var nd = Math.min(window.devicePixelRatio || 1, 2);
            var npw = Math.round(nw * nd), nph = Math.round(nh * nd);
            self.minigl.setSize(npw, nph);
            self.minigl.setOrthographicCamera();
            var nx = Math.max(12, Math.ceil(npw * self.densityMul));
            var ny = Math.max(12, Math.ceil(nph * self.densityMul));
            self.mesh.geometry.setTopology(nx, ny);
            self.mesh.geometry.setSize(npw, nph);
            self.uniforms.u_shadow_power.value = nw < 600 ? 5 : 6;
        };
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
        self.minigl.render();
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
        if (this._onResize) window.removeEventListener('resize', this._onResize);
    };

    /* ═══════════════════════════════════════
     * Canvas 2D Fallback
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
                radius: 0.3 + Math.random() * 0.4, color: self.colors[b]
            });
        }
        var parent = canvas.parentElement;
        self._onResize = function () {
            var d = Math.min(window.devicePixelRatio || 1, 2);
            var w = parent.offsetWidth || 300, h = parent.offsetHeight || 200;
            canvas.width = Math.round(w * d); canvas.height = Math.round(h * d);
            canvas.style.width = '100%'; canvas.style.height = '100%';
        };
        self._onResize();
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
            grad.addColorStop(0, b.color + 'cc'); grad.addColorStop(1, b.color + '00');
            ctx.globalCompositeOperation = 'lighter';
            ctx.fillStyle = grad; ctx.fillRect(0, 0, w, h);
        }
        ctx.globalCompositeOperation = 'source-over';
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
