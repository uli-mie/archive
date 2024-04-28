#include <stdlib.h>
#include <stdio.h>
#include <stdint.h>


int main(int argc, char * argv[])
{
    int64_t n, _n;
    int i, j, s, k;
    unsigned char * c;
    FILE * fp1, * fp2;
    
    fp1 = fopen("num.dat", "w");
    fp2 = fopen("bin.dat", "w");
    
    for (i = 0; i < 64; ++i) {
        _n = 1;
        for (j = 0; j < i; ++j) {
            _n *= 2;
        }
        for (s = -1; s <= 1; s+=2) {
            for (j = -1; j <= 1; ++j) {
                n = s*_n + j;
                fprintf(fp1, "%ld\n", n);
                c = (unsigned char *) (&n);
                for (k = 7; k >= 0; --k) {
                    fputc(c[k], fp2);
                }
            }
        }
    }
    
    fclose(fp2);
    fclose(fp1);
    
    return 0;
}
