#include <SDL/SDL.h>
#include <GL/gl.h>
#include <GL/glu.h>
#include <stdlib.h>
#include <stdio.h>
#include <math.h>

#define WIDTH 256
#define HEIGHT 256

/*
Copyright (c) 2007-2012 Ulrich Mierendorff

Permission is hereby granted, free of charge, to any person obtaining a
copy of this software and associated documentation files (the "Software"),
to deal in the Software without restriction, including without limitation
the rights to use, copy, modify, merge, publish, distribute, sublicense,
and/or sell copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
DEALINGS IN THE SOFTWARE.
*/

void load_img_to_tex(char *fname, GLuint *tex)
{
    char tmp[256];
    unsigned char *img;
    FILE *fp;
    int i;
    
    fp = fopen(fname, "rb");
    fgets(tmp, 255, fp);
    fgets(tmp, 255, fp);
    fgets(tmp, 255, fp);
    fgets(tmp, 255, fp);
    img = (unsigned char *) malloc(4*WIDTH*HEIGHT*sizeof(unsigned int));
    for (i = 0; i < WIDTH*HEIGHT; i++) {
        img[i*4+0] = (unsigned char) fgetc(fp);
        img[i*4+1] = (unsigned char) fgetc(fp);
        img[i*4+2] = (unsigned char) fgetc(fp);
        img[i*4+3] = 255;
    }
    fclose(fp);
    
    if (*tex) {
        glDeleteTextures(1, tex);
        *tex = 0;
    }
    glGenTextures(1, tex);
    glBindTexture(GL_TEXTURE_2D, *tex);
    glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_WRAP_S, GL_CLAMP);
    glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_WRAP_T, GL_CLAMP);
    glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MAG_FILTER, GL_LINEAR);
    glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MIN_FILTER, GL_LINEAR);
    glTexImage2D(GL_TEXTURE_2D, 0, GL_RGBA, WIDTH, HEIGHT, 0, GL_RGBA, GL_UNSIGNED_BYTE, img);

    free(img);
}

void blur_tex(GLuint tex, int passes)
{
    int i, x, y;
    
    glEnable(GL_BLEND);
    glBlendFunc(GL_SRC_ALPHA, GL_ONE_MINUS_SRC_ALPHA);
    glBindTexture(GL_TEXTURE_2D, tex);
    while (passes > 0) {
        i = 0;
        for (x = 0; x < 2; x++) {
            for (y = 0; y < 2; y++, i++) {
                glColor4f (1.0f,1.0f,1.0f,1.0 / (i+1));
                glBegin(GL_TRIANGLE_STRIP);
                    glTexCoord2f(0 + (x-0.5)/WIDTH, 1 + (y-0.5)/HEIGHT); glVertex2f(0, 0);
                    glTexCoord2f(0 + (x-0.5)/WIDTH, 0 + (y-0.5)/HEIGHT); glVertex2f(0, HEIGHT);
                    glTexCoord2f(1 + (x-0.5)/WIDTH, 1 + (y-0.5)/HEIGHT); glVertex2f(WIDTH, 0);
                    glTexCoord2f(1 + (x-0.5)/WIDTH, 0 + (y-0.5)/HEIGHT); glVertex2f(WIDTH, HEIGHT);
                glEnd ();
            }
        }
        glCopyTexImage2D(GL_TEXTURE_2D, 0, GL_RGBA, 0, 0, WIDTH, HEIGHT, 0);
        passes--;
    }
    glDisable(GL_BLEND);
}

void blur_tex_zoom(GLuint tex, int passes)
{
    int i;
    
    glEnable(GL_BLEND);
    glBlendFunc(GL_SRC_ALPHA, GL_ONE_MINUS_SRC_ALPHA);
    glBindTexture(GL_TEXTURE_2D, tex);
    while (passes > 0) {
        for (i = 0; i < 2; i++) {
            glColor4f(1.0f,1.0f,1.0f,1.0 / (i+1));
            glBegin(GL_TRIANGLE_STRIP);
                glTexCoord2f(0 - (i*0.5)/WIDTH, 1 + (i*0.5)/HEIGHT); glVertex2f(0, 0);
                glTexCoord2f(0 - (i*0.5)/WIDTH, 0 - (i*0.5)/HEIGHT); glVertex2f(0, HEIGHT);
                glTexCoord2f(1 + (i*0.5)/WIDTH, 1 + (i*0.5)/HEIGHT); glVertex2f(WIDTH, 0);
                glTexCoord2f(1 + (i*0.5)/WIDTH, 0 - (i*0.5)/HEIGHT); glVertex2f(WIDTH, HEIGHT);
            glEnd ();
        }
        glCopyTexImage2D(GL_TEXTURE_2D, 0, GL_RGBA, 0, 0, WIDTH, HEIGHT, 0);
        passes--;
    }
    glDisable(GL_BLEND);
}

void blur_tex_radial(GLuint tex, int passes)
{
    int i;
    
    glEnable(GL_BLEND);
    glBlendFunc(GL_SRC_ALPHA, GL_ONE_MINUS_SRC_ALPHA);
    glBindTexture(GL_TEXTURE_2D, tex);
    while (passes > 0) {
        for (i = 0; i < 2; i++) {
            glPushMatrix();
            glLoadIdentity();
                if (i == 1) {
                    glTranslatef(WIDTH/2, HEIGHT/2,0);
                    glRotatef(1, 0, 0, 1);
                    glTranslatef(-WIDTH/2, -HEIGHT/2,0);
                    
                }
                glColor4f(1.0f,1.0f,1.0f,1.0 / (i+1));
                glBegin(GL_TRIANGLE_STRIP);
                    glTexCoord2f(0.0f, 1.0f); glVertex2f(0, 0);
                    glTexCoord2f(0.0f, 0.0f); glVertex2f(0, HEIGHT);
                    glTexCoord2f(1.0f, 1.0f); glVertex2f(WIDTH, 0);
                    glTexCoord2f(1.0f, 0.0f); glVertex2f(WIDTH, HEIGHT);
                glEnd();
            glPopMatrix();
        }
        glCopyTexImage2D(GL_TEXTURE_2D, 0, GL_RGBA, 0, 0, WIDTH, HEIGHT, 0);
        passes--;
    }
    glDisable(GL_BLEND);
}

int main() {
    const SDL_VideoInfo *info = NULL;
    int bpp, flags;
    int mouse_x, mouse_y;
    int steps = 0;
    int time;
    SDL_Event event;
    GLuint tex = 0, tex2 = 0;
    
    SDL_Init(SDL_INIT_VIDEO);
    info = SDL_GetVideoInfo();
    
    if (!info) {
        printf("Video query failed: %s\n", SDL_GetError());
        return 0;
    }
    
    bpp = info->vfmt->BitsPerPixel;
    SDL_GL_SetAttribute(SDL_GL_RED_SIZE, 8);
    SDL_GL_SetAttribute(SDL_GL_GREEN_SIZE, 8);
    SDL_GL_SetAttribute(SDL_GL_BLUE_SIZE, 8);
    SDL_GL_SetAttribute(SDL_GL_DEPTH_SIZE, 16);
    SDL_GL_SetAttribute(SDL_GL_DOUBLEBUFFER, 1);
    
    flags = SDL_OPENGL;
    if (!SDL_SetVideoMode(WIDTH, HEIGHT, bpp, flags)) {
        printf("Video mode set failed: %s\n", SDL_GetError());
        return 0;
    }
    
    SDL_GetMouseState(&mouse_x, &mouse_y);
    
    glEnable(GL_TEXTURE_2D);
    
    load_img_to_tex("image.ppm", &tex);
    load_img_to_tex("image.ppm", &tex2);
    while (1) {
        glClearColor(0.0f, 0.0f, 0.0f, 1.0f);
        glClear(GL_COLOR_BUFFER_BIT | GL_DEPTH_BUFFER_BIT);
        glDisable(GL_DEPTH_TEST);
        glViewport(0, 0, WIDTH, HEIGHT);
        glMatrixMode(GL_PROJECTION);
        glPushMatrix();
            glLoadIdentity();
            glOrtho(0.0f, WIDTH, HEIGHT, 0.0f, -1.0f, 1.0f);
            glMatrixMode(GL_MODELVIEW);
            glLoadIdentity();
            while (SDL_PollEvent(&event)) {
                switch (event.type) {
                    case SDL_KEYDOWN:
                        if ('0' <= event.key.keysym.sym && event.key.keysym.sym <= '9') {
                            time = SDL_GetTicks();
                            blur_tex(tex, 1<<(event.key.keysym.sym-'0'));
                            //blur_tex_zoom(tex, 1<<(event.key.keysym.sym-'0'));
                            //blur_tex_radial(tex, 1<<(event.key.keysym.sym-'0'));
                            glFlush();
                            glFinish();
                            printf("time: %ims for %i passes\n", SDL_GetTicks()-time, 1<<(event.key.keysym.sym-'0'));
                            glClear(GL_COLOR_BUFFER_BIT | GL_DEPTH_BUFFER_BIT);
                            ++steps;
                        } else if (event.key.keysym.sym == 'r') {
                            load_img_to_tex("image.ppm", &tex);
                            steps = 0;
                        }
                        break;
                    case SDL_QUIT:
                        return 0;
                        break;
                    default:
                        break;
                }
            }
            glEnable(GL_BLEND);
            glBlendFunc(GL_SRC_ALPHA, GL_ONE_MINUS_SRC_ALPHA);
            glColor4f(1.0f,1.0f,1.0f,1.0f);
            glBindTexture(GL_TEXTURE_2D, tex2);
            glBegin(GL_TRIANGLE_STRIP);
                glTexCoord2f (0.0,0.0); glVertex2f (0.0,0.0);
                glTexCoord2f (0.0,1.0); glVertex2f (0.0,0+HEIGHT);
                glTexCoord2f (1.0,0.0); glVertex2f (0+WIDTH,0.0);
                glTexCoord2f (1.0,1.0); glVertex2f (0+WIDTH,HEIGHT);
            glEnd();
            glColor4f(1.0f,1.0f,1.0f,1.0f);
            glBindTexture(GL_TEXTURE_2D, tex);
            glBegin(GL_TRIANGLE_STRIP);
                glTexCoord2f (0.0,0.0); glVertex2f (0.0,0.0);
                glTexCoord2f (0.0,1.0); glVertex2f (0.0,0+HEIGHT);
                glTexCoord2f (1.0,0.0); glVertex2f (0+WIDTH,0.0);
                glTexCoord2f (1.0,1.0); glVertex2f (0+WIDTH,HEIGHT);
            glEnd();
            
            glMatrixMode(GL_PROJECTION);
        glPopMatrix();
        glMatrixMode(GL_MODELVIEW);
        
        glFlush();
        glFinish();
        SDL_GL_SwapBuffers();
    }
    
    glDeleteTextures(1, &tex);
    return 0;
}
