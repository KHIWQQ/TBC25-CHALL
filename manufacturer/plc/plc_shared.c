#ifdef _FORTIFY_SOURCE
#undef _FORTIFY_SOURCE
#endif
#define _FORTIFY_SOURCE 0

#include <stdint.h>
#include <string.h>

static volatile int g_compromised = 0;
static volatile uint8_t g_conveyor_run = 1;    
static volatile uint8_t g_emergency_ok = 1;    
static volatile uint32_t g_quality_score = 98; // percent

void plc_reset_state(void)
{
    g_compromised = 0;
    g_conveyor_run = 1;
    g_emergency_ok = 1;
    g_quality_score = 98;
}

int plc_compromised(void) { return g_compromised; }
uint8_t get_conveyor_run(void) { return g_conveyor_run; }
uint8_t get_emergency_ok(void) { return g_emergency_ok; }
uint32_t get_quality_score(void) { return g_quality_score; }

static void parse_pairs(const char *buf)
{
    const char *p = buf;
    while (*p)
    {
        const char *eq = strchr(p, '=');
        if (!eq)
            break;
        char key[16], val[16];
        size_t klen = (size_t)(eq - p);
        if (klen > 15)
            klen = 15;
        memcpy(key, p, klen);
        key[klen] = '\0';

        const char *sep = strpbrk(eq + 1, ",\n\r");
        size_t vlen = sep ? (size_t)(sep - (eq + 1)) : strlen(eq + 1);
        if (vlen > 15)
            vlen = 15;
        memcpy(val, eq + 1, vlen);
        val[vlen] = '\0';

        if (strcmp(key, "RUN") == 0)
        {
            g_conveyor_run = (uint8_t)(val[0] == '1');
        }
        else if (strcmp(key, "ESTOP_OK") == 0)
        {
            g_emergency_ok = (uint8_t)(val[0] == '1');
        }
        else if (strcmp(key, "QUALITY") == 0)
        {
            int q = 0;
            for (size_t i = 0; i < vlen; i++)
                if (val[i] >= '0' && val[i] <= '9')
                    q = q * 10 + (val[i] - '0');
            if (q < 0)
                q = 0;
            if (q > 100)
                q = 100;
            g_quality_score = (uint32_t)q;
        }

        p = sep ? (sep + 1) : (eq + 1 + vlen);
    }
}

#if defined(__GNUC__)
__attribute__((no_stack_protector))
#endif
void
process_production_order(const char *order)
{
    struct
    {
        char buf[42];
        volatile unsigned char spill[128];
    } frame;

    strcpy(frame.buf, order);
    parse_pairs(frame.buf);

    if (strlen(order) >= sizeof(frame.buf))
    {
        g_compromised = 1;
        g_conveyor_run = 0;
        g_emergency_ok = 0;
        if (g_quality_score > 30)
            g_quality_score -= 30;
    }
}
