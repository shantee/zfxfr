
precision highp float;

varying vec2 vUv0;
//uniform vec2 aUv0;
varying vec3 vNormal;
//varying vec3 vNormalW;
varying vec3 vPositionW;
uniform vec3 iResolution;

uniform float time;



#define AA

vec2 rotatePosition(vec2 pos, vec2 centre, float angle) {
    float sinAngle = sin(angle);
    float cosAngle = cos(angle);
    pos -= centre;
    vec2 rotatedPos;
    rotatedPos.x = pos.x * cosAngle - pos.y * sinAngle;
    rotatedPos.y = pos.x * sinAngle + pos.y * cosAngle;
    rotatedPos += centre;
    return rotatedPos;
}

void main(void) {
    const float PI = 3.14;
    float amplitude = 0.9;
    float frequency = 0.4;

    //Centre of the screen
    vec2 centre = vec2(0.5, 0.5);

    //Repetition of stripe unit
    float tilingFactor = 10.0;

    float angle = -PI/4.0;

    vec3 col_1 = vec3(1.0, 0.05, 0.05);
    vec3 col_2 = vec3(1.0, 1.0, 0.0);    
    vec3 col_3 = vec3(0.2, 1.0, 0.2);
    vec3 col_4 = vec3(0.1, 0.45, 1.0);

    vec3 col = vec3(0);

    vec2 fC = vUv0.xy;

    #ifdef AA
    for(int i = -1; i <= 1; i++) {
        for(int j = -1; j <= 1; j++) {

            fC = vUv0.xy+vec2(i,j)/3.0;

            #endif

            //Normalized pixel coordinates (from 0 to 1)
            vec2 uv = vUv0;

            //The ratio of the width and height of the screen
            float widthHeightRatio = iResolution.x/iResolution.y;

            //Adjust vertical pos to make the width of the stripes 
            //transform uniformly regardless of orientation
            uv.y /= widthHeightRatio;

            //Rotate pos around centre by specified radians
            uv = rotatePosition(uv, centre, angle);

            //Move frame along rotated y direction
            uv.y -= 0.75*time;

            vec2 position = uv * tilingFactor;
            position.x += amplitude * sin(frequency * position.y);

            //Set stripe colours
            int value = int(floor(fract(position.x) * 4.0));

            if(value == 0){col += col_1;}
            if(value == 1){col += col_2;}
            if(value == 2){col += col_3;}
            if(value == 3){col += col_4;}

            #ifdef AA
        }
    }

    col /= 9.0;

    #endif
    
    //Gamma
    col = pow(col, vec3(0.4545));

    //Fragment colour
    gl_FragColor = vec4(col,1.0);
}
/*
void main(void) {
    vec2 uv = vUv0.xy;
    vec3 bg =  vec3(0.17,0.28,1);
    vec3 color1 = vec3(0.76,0.02,0.34);
    vec3 color2 = vec3(0.05,0.42,0.91);
    vec3 color3 = vec3(0.01,0.77,0.96);
    vec3 color4 = vec3(0.82,0.33,0.71); // rose
    vec3 color5 = vec3(0,0.28,0.53); // bleu foncé 

    vec3 pixel = bg;
    
    float t = mod(time/5.0, 1.0);
    
    if(sin(uv.x)*uv.y > t -1.0 && uv.x < t - 0.9){
    	pixel = color1;
    } 
    else if(sin(uv.x)*uv.y > t - 0.8 && uv.x < t - 0.7){
    	pixel = color2;
    } 
    else if(cos(uv.x)*uv.y > t - 0.6 && uv.x < t - 0.5){
    	pixel = color3;
    } 
    else if(sin(uv.y)*uv.x > t - 0.4 && uv.x < t - 0.3){
    	pixel = color4;
    } 
    else if(uv.x > t - 0.2 && uv.x < t - 0.1){
    	pixel = color5;
    }  
    else if(uv.x > t && uv.x < 0.1 + t){
    	pixel = color1;
    }
    else if(uv.x > 0.2 + t && uv.x < 0.3 + t){
    	pixel = color2;
    }
    else if(uv.x > 0.4 + t && uv.x < 0.5 + t){
    	pixel = color3;
    }
    else if(uv.x > 0.6 + t && uv.x < 0.7 + t){
    	pixel = color4;
    }
    else if(uv.x > 0.8 + t && uv.x < 0.9 + t){
    	pixel = color5;
    }
    
    gl_FragColor = vec4(pixel, 1.0);
  
}
*/

