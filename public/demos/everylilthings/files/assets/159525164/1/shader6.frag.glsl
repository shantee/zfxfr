varying vec2 vUv0;

uniform sampler2D uDiffuseMap;
uniform sampler2D uHeightMap;
uniform float uTime;

#define pi 3.14159265359

#define float2 vec2
#define float3 vec3
#define float4 vec4
float time = uTime;

float saw(float x)
{
    return abs(fract(x)-0.5)*2.0;
}

float dw(float2 p, float2 c, float t)
{
    return sin(length(p-c)-t);
}

float dw1(float2 uv)
{
    float v=0.0;
    float t=time*2.0;
    v+=dw(uv,float2(sin(t*0.07)*30.0,cos(t*0.04)*20.0),t*1.3);
    v+=dw(uv,float2(cos(t*0.13)*30.0,sin(t*0.14)*20.0),t*1.6+1.0);
    v+=dw(uv,float2( 18,-15),t*0.7+2.0);
    v+=dw(uv,float2(-18, 15),t*1.1-1.0);
    return v/4.0;
}

float fun(float x, float y)
{
	return dw1(float2(x-0.5,y-0.5)*80.0);
}

float3 duv(float2 uv)
{
    float x=uv.x;
    float y=uv.y;
    float v=fun(x,y);
    float d=1.0/400.0;
	float dx=(v-fun(x+d,y))/d;
	float dy=(v-fun(x,y+d))/d;
    float a=atan(dx,dy)/pi/2.0;
    return float3(v,0,(v*4.0+a));
}

void main(void)
{
  
  vec2 uv =vUv0;
  float height = texture2D(uHeightMap, vUv0).r;
vec4 color = texture2D(uDiffuseMap, vUv0);
   float3 h=duv(uv);
    float sp=saw(h.z+time*1.3);
    //sp=(sp>0.5)?0.3:1.0;
    sp=clamp((sp-0.25)*2.0,0.5,1.0);
    gl_FragColor = float4((h.x+0.5)*sp*color.r, (0.3+saw(h.x+0.5)*0.6)*sp*color.g, (0.6-h.x)*sp*color.b, 1.0);

/*
 
 	
// main code, *original shader by: 'Plasma' by Viktor Korsun (2011)
float x = p.x;
float y = p.y;
float mov0 = x+y+cos(sin(time)*2.0)*100.+sin(x/100.)*1000.;
float mov1 = y / 0.9 +  time;
float mov2 = x / 0.2;
float c1 = abs(sin(mov1+time)/2.+mov2/2.-mov1-mov2+time);
float c2 = abs(sin(c1+sin(mov0/1000.+time)+sin(y/40.+time)+sin((x+y)/100.)*3.));
float c3 = abs(sin(c2+cos(mov1+mov2+c2)+cos(mov2)+sin(x/1000.)));
//fragColor = vec4(c1,c2,c3,1);
    gl_FragColor = vec4(color.r*c1,c2*color.g,sin(time)*color.b*c1,1.0);
*/
}